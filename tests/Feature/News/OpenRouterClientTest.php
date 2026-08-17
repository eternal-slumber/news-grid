<?php

use App\Models\News;
use App\Models\NewsRewrite;
use App\News\NewsRewriteData;
use App\News\OpenRouterClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

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

test('it generates and validates all moods in two structured requests', function () {
    Http::fakeSequence()
        ->push(clientOpenRouterResponse(clientRewritePayload()))
        ->push(clientOpenRouterResponse([
            'results' => [
                NewsRewrite::MOOD_POSITIVE => [
                    'valid' => true,
                    'tone_matches' => true,
                    'issues' => [],
                ],
                NewsRewrite::MOOD_IRONIC => [
                    'valid' => true,
                    'tone_matches' => true,
                    'issues' => [],
                ],
                NewsRewrite::MOOD_SAD => [
                    'valid' => false,
                    'tone_matches' => true,
                    'issues' => [[
                        'type' => 'unsupported_claims',
                        'description' => 'Добавлен новый факт.',
                    ]],
                ],
            ],
        ]));

    $news = News::factory()->make([
        'title' => 'Исходный заголовок',
        'lead' => 'Исходный лид',
        'content' => 'Исходный полный текст.',
    ]);

    $client = app(OpenRouterClient::class);
    $rewrites = $client->rewriteAll($news);
    $validations = $client->validate($news, $rewrites);
    $generationRequest = Http::recorded()[0][0];
    $generationSystemPrompt = $generationRequest['messages'][0]['content'];
    $generationUserPrompt = $generationRequest['messages'][1]['content'];

    expect($rewrites)->toHaveCount(3)
        ->and($rewrites[NewsRewrite::MOOD_IRONIC]->title)->toBe('ironic заголовок')
        ->and($validations[NewsRewrite::MOOD_POSITIVE]->passed)->toBeTrue()
        ->and($validations[NewsRewrite::MOOD_POSITIVE]->message)
        ->toBe('Факты сохранены, эмоциональный окрас соответствует режиму.')
        ->and($validations[NewsRewrite::MOOD_SAD]->passed)->toBeFalse()
        ->and($validations[NewsRewrite::MOOD_SAD]->message)
        ->toContain('unsupported_claims')
        ->and($generationSystemPrompt)
        ->toContain('единственный источник фактов')
        ->toContain('принадлежность утверждений источникам')
        ->toContain('не создавай цитаты')
        ->toContain('всегда выбирай точность')
        ->and($generationUserPrompt)
        ->toContain('ИНТЕНСИВНОСТЬ СТИЛИЗАЦИИ: умеренная')
        ->toContain('<source>')
        ->toContain('Не придумывай положительные последствия')
        ->toContain('Не придумывай жертв')
        ->toContain('Не придумывай действия');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader(
        'Authorization',
        'Bearer test-key',
    ) && $request['model'] === 'test-model'
        && $request['reasoning']['effort'] === 'low'
        && $request['reasoning']['exclude'] === true
        && $request['provider']['require_parameters'] === true
        && $request['response_format']['type'] === 'json_schema');
});

test('it sends only standard OpenAI fields to LM Studio', function () {
    config()->set('services.openrouter.provider', 'lmstudio');
    config()->set('services.openrouter.key');
    config()->set('services.openrouter.model', 'google/gemma-4-e4b');
    config()->set(
        'services.openrouter.url',
        'http://host.docker.internal:1234/v1/chat/completions',
    );

    Http::fake([
        'host.docker.internal:1234/*' => Http::response(
            clientOpenRouterResponse(clientRewritePayload()),
        ),
    ]);

    app(OpenRouterClient::class)->rewriteAll(News::factory()->make());

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'http://host.docker.internal:1234/v1/chat/completions'
        && ! $request->hasHeader('Authorization')
        && $request['model'] === 'google/gemma-4-e4b'
        && ! isset($request['reasoning'])
        && ! isset($request['provider'])
        && $request['response_format']['type'] === 'json_schema');
});

test('it rejects invalid JSON from AI provider', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'not-json'],
            ]],
        ]),
    ]);

    $news = News::factory()->make();

    expect(fn () => app(OpenRouterClient::class)->rewriteAll($news))
        ->toThrow(RuntimeException::class, 'AI provider returned invalid JSON.');
});

test('it rejects an unknown validation issue type', function () {
    Http::fake([
        'https://openrouter.test/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'results' => [
                            NewsRewrite::MOOD_IRONIC => [
                                'valid' => false,
                                'tone_matches' => true,
                                'issues' => [[
                                    'type' => 'unknown',
                                    'description' => 'Ошибка.',
                                ]],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]),
    ]);

    $news = News::factory()->make();
    $rewrite = new NewsRewriteData('Заголовок', null, 'Текст');

    expect(fn () => app(OpenRouterClient::class)->validate($news, [
        NewsRewrite::MOOD_IRONIC => $rewrite,
    ]))
        ->toThrow(ValidationException::class);
});

test('it retries a rate limited request using retry after', function () {
    Http::fakeSequence()
        ->push([
            'error' => ['message' => 'Rate limited'],
        ], 429, ['Retry-After' => '0'])
        ->push(clientOpenRouterResponse(clientRewritePayload()));

    $rewrites = app(OpenRouterClient::class)->rewriteAll(News::factory()->make());

    expect($rewrites[NewsRewrite::MOOD_IRONIC]->title)->toBe('ironic заголовок');
    Http::assertSentCount(2);
});

/**
 * @return array<string, array{title: string, lead: string, content: string}>
 */
function clientRewritePayload(): array
{
    return collect(NewsRewrite::MOODS)
        ->mapWithKeys(fn (string $mood): array => [
            $mood => [
                'title' => "{$mood} заголовок",
                'lead' => "{$mood} лид",
                'content' => "{$mood} текст.",
            ],
        ])
        ->all();
}

/**
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
function clientOpenRouterResponse(array $content): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => json_encode($content, JSON_THROW_ON_ERROR),
            ],
        ]],
    ];
}
