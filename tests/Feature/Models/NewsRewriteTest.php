<?php

use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Database\QueryException;

test('a news rewrite belongs to news and casts validation result', function () {
    $news = News::factory()->create();

    $rewrite = NewsRewrite::factory()
        ->for($news)
        ->create(['mood' => 'positive']);

    $this->assertModelExists($rewrite);

    expect($rewrite->news->is($news))->toBeTrue()
        ->and($news->rewrites()->sole()->is($rewrite))->toBeTrue()
        ->and($rewrite->status)->toBe('pending')
        ->and($rewrite->validation_passed)->toBeNull();

    $rewrite->update([
        'status' => 'completed',
        'validation_passed' => true,
    ]);

    expect($rewrite->fresh()->validation_passed)->toBeTrue();
});

test('a news and mood pair must be unique', function () {
    $news = News::factory()->create();

    NewsRewrite::factory()
        ->for($news)
        ->create(['mood' => NewsRewrite::MOOD_IRONIC]);

    expect(fn () => NewsRewrite::factory()
        ->for($news)
        ->create(['mood' => NewsRewrite::MOOD_IRONIC]))
        ->toThrow(QueryException::class);
});

test('rewrites are deleted with their news', function () {
    $news = News::factory()->create();
    $rewrite = NewsRewrite::factory()->for($news)->create();

    $news->delete();

    $this->assertModelMissing($rewrite);
});
