<?php

use App\Jobs\GenerateNewsRewritesJob;
use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('queue.default', 'database');
});

test('it deletes news, rewrites, and queued jobs', function () {
    $news = News::factory()->create();
    NewsRewrite::factory()->for($news)->create();
    $queue = Queue::connection('database');
    $queue->push(new GenerateNewsRewritesJob($news->id));

    $this->artisan('news:clear', ['--force' => true])
        ->expectsOutput('Deleted: 1 news, 1 rewrites; cleared jobs: 1.')
        ->assertSuccessful();

    expect(News::query()->count())->toBe(0)
        ->and(NewsRewrite::query()->count())->toBe(0)
        ->and($queue->size())->toBe(0);
});

test('it keeps data when deletion is cancelled', function () {
    $news = News::factory()->create();

    $this->artisan('news:clear')
        ->expectsConfirmation('Delete 1 news, 0 rewrites, and 0 queued jobs?', 'no')
        ->expectsOutput('Cancelled.')
        ->assertSuccessful();

    $this->assertModelExists($news);
});
