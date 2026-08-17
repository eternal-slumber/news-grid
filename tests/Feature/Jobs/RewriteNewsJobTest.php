<?php

use App\Jobs\GenerateNewsRewritesJob;
use App\Jobs\ValidateNewsRewritesJob;
use App\Models\News;
use App\Models\NewsRewrite;
use App\News\OpenRouterClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.openrouter', [
        'provider' => 'openrouter',
        'key' => 'test-key',
        'model' => 'test-model',
        'url' => 'https://openrouter.test/chat/completions',
        'connect_timeout' => 2,
        'timeout' => 20,
        'rewrite_max_tokens' => 12000,
        'validation_max_tokens' => 3000,
    ]);

    Http::preventStrayRequests();
});

test('queue visibility and unique locks exceed AI job timeouts', function () {
    $generationJob = new GenerateNewsRewritesJob(1);
    $validationJob = new ValidateNewsRewritesJob(1);
    $retryAfter = config('queue.connections.database.retry_after');

    expect($generationJob->timeout)->toBe(600)
        ->and($validationJob->timeout)->toBe(7200)
        ->and($retryAfter)->toBe(7500)
        ->and($retryAfter)->toBeGreaterThan($generationJob->timeout)
        ->and($retryAfter)->toBeGreaterThan($validationJob->timeout)
        ->and($generationJob->uniqueFor)->toBeGreaterThanOrEqual($retryAfter)
        ->and($validationJob->uniqueFor)->toBeGreaterThanOrEqual($retryAfter);
});

test('generation stores all moods and queues one validation job', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response(
            jobOpenRouterResponse(jobRewritePayload()),
        ),
    ]);
    Queue::fake();
    $news = News::factory()->create();

    (new GenerateNewsRewritesJob($news->id))->handle(app(OpenRouterClient::class));

    $rewrites = $news->rewrites()->get()->keyBy('mood');

    expect($rewrites)->toHaveCount(3)
        ->and($rewrites[NewsRewrite::MOOD_POSITIVE]->status)
        ->toBe(NewsRewrite::STATUS_PROCESSING)
        ->and($rewrites[NewsRewrite::MOOD_POSITIVE]->title)
        ->toBe('positive заголовок');

    Queue::assertPushed(
        ValidateNewsRewritesJob::class,
        fn (ValidateNewsRewritesJob $job): bool => $job->newsId === $news->id,
    );
    Http::assertSentCount(1);
});

test('validation completes all moods in one request', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response(
            jobOpenRouterResponse(jobValidationPayload()),
        ),
    ]);
    $news = newsWithGeneratedRewrites();

    (new ValidateNewsRewritesJob($news->id))->handle(app(OpenRouterClient::class));

    expect($news->rewrites()->where('status', NewsRewrite::STATUS_COMPLETED)->count())
        ->toBe(3)
        ->and($news->rewrites()->where('validation_passed', true)->count())
        ->toBe(3);
    Http::assertSentCount(1);
});

test('validation repairs only a rejected mood and sends local number warnings', function () {
    Http::fakeSequence()
        ->push(jobOpenRouterResponse(jobValidationPayload([
            NewsRewrite::MOOD_IRONIC => [
                'valid' => false,
                'tone_matches' => false,
                'issues' => [],
            ],
        ])))
        ->push(jobOpenRouterResponse([
            'title' => 'Исправленный заголовок',
            'lead' => null,
            'content' => 'В 2026 году событие состоялось.',
        ]))
        ->push(jobOpenRouterResponse(jobValidationPayload([], [NewsRewrite::MOOD_IRONIC])));
    $news = newsWithGeneratedRewrites();
    $news->rewrites()
        ->where('mood', NewsRewrite::MOOD_IRONIC)
        ->update(['content' => 'В 2026 году было 99 событий.']);

    (new ValidateNewsRewritesJob($news->id))->handle(app(OpenRouterClient::class));

    $ironic = $news->rewrites()->where('mood', NewsRewrite::MOOD_IRONIC)->sole();
    $requests = Http::recorded();
    $firstRequest = $requests[0][0];
    $repairRequest = $requests[1][0];

    expect($ironic->status)->toBe(NewsRewrite::STATUS_COMPLETED)
        ->and($ironic->title)->toBe('Исправленный заголовок')
        ->and($ironic->validation_message)
        ->toBe('Факты сохранены, эмоциональный окрас соответствует режиму.')
        ->and($firstRequest['messages'][1]['content'])
        ->toContain('новые числа: 99')
        ->and($firstRequest['messages'][1]['content'])
        ->toContain('Текст полностью совпадает')
        ->and($repairRequest['messages'][1]['content'])
        ->toContain('tone_mismatch');
    Http::assertSentCount(3);
});

test('it records API failures before retrying', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response([
            'error' => ['message' => 'Unauthorized'],
        ], 401),
    ]);

    $news = News::factory()->create();

    expect(fn () => (new GenerateNewsRewritesJob($news->id))->handle(
        app(OpenRouterClient::class),
    ))->toThrow(RequestException::class);

    expect($news->rewrites()->where('status', NewsRewrite::STATUS_PENDING)->count())
        ->toBe(3)
        ->and($news->rewrites()->whereNotNull('error_message')->count())
        ->toBe(3);
});

test('it does not retry an exhausted daily free model limit', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response([
            'error' => ['message' => 'Rate limit exceeded: free-models-per-day.'],
        ], 429),
    ]);
    $news = News::factory()->create();
    $job = (new GenerateNewsRewritesJob($news->id))
        ->withFakeQueueInteractions();

    $job->handle(app(OpenRouterClient::class));

    $job->assertFailed();
    expect($news->rewrites()->where('status', NewsRewrite::STATUS_FAILED)->count())
        ->toBe(3);
    Http::assertSentCount(1);
});

/**
 * @param  array<string, array{valid: bool, tone_matches: bool, issues: list<array{type: string, description: string}>}>  $overrides
 * @param  list<string>|null  $moods
 * @return array{results: array<string, array{valid: bool, tone_matches: bool, issues: list<array{type: string, description: string}>}>}
 */
function jobValidationPayload(array $overrides = [], ?array $moods = null): array
{
    $results = collect($moods ?? NewsRewrite::MOODS)
        ->mapWithKeys(fn (string $mood): array => [
            $mood => $overrides[$mood] ?? [
                'valid' => true,
                'tone_matches' => true,
                'issues' => [],
            ],
        ])
        ->all();

    return ['results' => $results];
}

/**
 * @return array<string, array{title: string, lead: null, content: string}>
 */
function jobRewritePayload(): array
{
    return collect(NewsRewrite::MOODS)
        ->mapWithKeys(fn (string $mood): array => [
            $mood => [
                'title' => "{$mood} заголовок",
                'lead' => null,
                'content' => "{$mood} текст.",
            ],
        ])
        ->all();
}

function newsWithGeneratedRewrites(): News
{
    $news = News::factory()->create([
        'title' => 'Событие 2026 года',
        'lead' => null,
        'content' => 'В 2026 году событие состоялось.',
    ]);

    foreach (NewsRewrite::MOODS as $mood) {
        NewsRewrite::factory()->for($news)->create([
            'mood' => $mood,
            'status' => NewsRewrite::STATUS_PROCESSING,
            'title' => "{$mood} заголовок 2026",
            'content' => 'В 2026 году событие состоялось.',
        ]);
    }

    return $news;
}

/**
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
function jobOpenRouterResponse(array $content): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => json_encode($content, JSON_THROW_ON_ERROR),
            ],
        ]],
    ];
}
