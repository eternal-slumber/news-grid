<?php

use App\Jobs\GenerateNewsRewritesJob;
use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Support\Facades\Queue;

test('it queues one generation job without duplicate rewrites', function () {
    Queue::fake();

    $news = News::factory()->create();

    $this->artisan('news:rewrite', ['news' => $news->id])
        ->expectsOutput('Queued: 1; skipped: 0.')
        ->assertSuccessful();

    expect($news->rewrites()->pluck('mood')->sort()->values()->all())
        ->toBe(collect(NewsRewrite::MOODS)->sort()->values()->all());

    Queue::assertPushed(GenerateNewsRewritesJob::class, 1);

    $this->artisan('news:rewrite', ['news' => $news->id])
        ->expectsOutput('Queued: 1; skipped: 0.')
        ->assertSuccessful();

    expect($news->rewrites()->count())->toBe(3);
    Queue::assertPushed(GenerateNewsRewritesJob::class, 1);
});

test('it safely requeues orphaned pending rewrites', function () {
    Queue::fake();

    $news = News::factory()->create();

    foreach (NewsRewrite::MOODS as $mood) {
        NewsRewrite::factory()->for($news)->create([
            'mood' => $mood,
            'status' => NewsRewrite::STATUS_PENDING,
        ]);
    }

    $this->artisan('news:rewrite', [
        'news' => $news->id,
        '--force' => true,
    ])
        ->expectsOutput('Queued: 1; skipped: 0.')
        ->assertSuccessful();

    Queue::assertPushed(GenerateNewsRewritesJob::class, 1);
});

test('it rolls back rewrite state when dispatch fails', function () {
    $queue = Queue::fake();
    $queue->beforePushing(fn () => throw new RuntimeException('Queue unavailable.'));

    $news = News::factory()->create();

    expect(fn () => $this->artisan('news:rewrite', ['news' => $news->id])->run())
        ->toThrow(RuntimeException::class, 'Queue unavailable.');

    expect($news->rewrites()->count())->toBe(0);
});

test('it rejects an unknown news ID', function () {
    Queue::fake();

    $this->artisan('news:rewrite', ['news' => 999999])
        ->expectsOutput('News 999999 was not found.')
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('it queues rewrites for the latest news', function () {
    Queue::fake();

    $oldestNews = News::factory()->create([
        'published_at' => now()->subHours(2),
    ]);
    $latestNews = News::factory()->create([
        'published_at' => now(),
    ]);

    $this->artisan('news:rewrite', ['--latest' => 1])
        ->expectsOutput('Queued: 1; skipped: 0.')
        ->assertSuccessful();

    expect($oldestNews->rewrites()->count())->toBe(0)
        ->and($latestNews->rewrites()->count())->toBe(3);

    Queue::assertPushed(GenerateNewsRewritesJob::class, 1);
});

test('it force requeues completed rewrites', function () {
    Queue::fake();

    $news = News::factory()->create();
    NewsRewrite::factory()->for($news)->create([
        'mood' => NewsRewrite::MOOD_IRONIC,
        'status' => NewsRewrite::STATUS_COMPLETED,
        'title' => 'Old rewrite',
        'content' => 'Old content',
        'validation_passed' => true,
    ]);

    $this->artisan('news:rewrite', [
        'news' => $news->id,
        '--force' => true,
    ])
        ->expectsOutput('Queued: 1; skipped: 0.')
        ->assertSuccessful();

    $ironicRewrite = $news->rewrites()
        ->where('mood', NewsRewrite::MOOD_IRONIC)
        ->sole();

    expect($ironicRewrite->status)->toBe(NewsRewrite::STATUS_PENDING)
        ->and($ironicRewrite->title)->toBeNull()
        ->and($ironicRewrite->content)->toBeNull()
        ->and($ironicRewrite->validation_passed)->toBeNull();

    Queue::assertPushed(GenerateNewsRewritesJob::class, 1);
});

test('it requires either a news ID or latest option', function () {
    Queue::fake();

    $this->artisan('news:rewrite')
        ->expectsOutput('Provide either a News ID or --latest, but not both.')
        ->assertFailed();

    Queue::assertNothingPushed();
});
