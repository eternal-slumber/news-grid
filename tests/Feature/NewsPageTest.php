<?php

use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('news application is served from the root page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('news/index'));
});

test('legacy news path redirects to the root page', function () {
    $this->get('/news')->assertRedirect('/');
});

test('latest news are displayed publicly', function () {
    News::factory()->create([
        'title' => 'Older news',
        'published_at' => now()->subHour(),
    ]);

    News::factory()->create([
        'title' => 'Latest news',
        'published_at' => now(),
    ]);

    $this->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/index')
            ->has('news', 2)
            ->where('news.0.title', 'Latest news')
            ->where('news.1.title', 'Older news'),
        );
});

test('news index includes validated rewrite previews without full texts', function () {
    $news = News::factory()->create();

    NewsRewrite::factory()->for($news)->create([
        'mood' => NewsRewrite::MOOD_IRONIC,
        'status' => NewsRewrite::STATUS_COMPLETED,
        'title' => 'Ironic rewrite',
        'lead' => 'Ironic lead',
        'content' => 'Ironic content',
        'validation_passed' => true,
        'validation_message' => 'Facts preserved.',
    ]);

    NewsRewrite::factory()->for($news)->create([
        'mood' => NewsRewrite::MOOD_POSITIVE,
        'status' => NewsRewrite::STATUS_PENDING,
        'validation_passed' => null,
    ]);

    NewsRewrite::factory()->for($news)->create([
        'mood' => NewsRewrite::MOOD_SAD,
        'status' => NewsRewrite::STATUS_COMPLETED,
        'validation_passed' => false,
    ]);

    $this->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('news.0.original_url')
            ->missing('news.0.content')
            ->has('news.0.rewrites', 1)
            ->where('news.0.rewrites.0.mood', NewsRewrite::MOOD_IRONIC)
            ->where('news.0.rewrites.0.status', NewsRewrite::STATUS_COMPLETED)
            ->where('news.0.rewrites.0.title', 'Ironic rewrite')
            ->where('news.0.rewrites.0.lead', 'Ironic lead')
            ->where('news.0.rewrites.0.validation_passed', true)
            ->missing('news.0.rewrites.0.content')
            ->missing('news.0.rewrites.0.validation_message'),
        );
});

test('a news article is displayed on a separate page', function () {
    $news = News::factory()->create([
        'title' => 'Original title',
        'content' => 'Original content',
        'original_url' => 'https://example.com/original-story',
        'source_name' => 'Example News',
    ]);

    foreach ([
        NewsRewrite::MOOD_POSITIVE => 'Positive rewrite',
        NewsRewrite::MOOD_SAD => 'Sad rewrite',
    ] as $mood => $title) {
        NewsRewrite::factory()->for($news)->create([
            'mood' => $mood,
            'status' => NewsRewrite::STATUS_COMPLETED,
            'title' => $title,
            'content' => "{$title} content",
            'validation_passed' => true,
        ]);
    }

    NewsRewrite::factory()->for($news)->create([
        'mood' => NewsRewrite::MOOD_IRONIC,
        'status' => NewsRewrite::STATUS_FAILED,
        'validation_passed' => false,
    ]);

    $this->get(route('news.show', $news))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/show')
            ->where('news.id', $news->id)
            ->where('news.title', 'Original title')
            ->where('news.content', 'Original content')
            ->where('news.original_url', 'https://example.com/original-story')
            ->where('news.source_name', 'Example News')
            ->has('news.rewrites', 2)
            ->where('news.rewrites', fn (Collection $rewrites): bool => $rewrites
                ->pluck('mood')
                ->sort()
                ->values()
                ->all() === [
                    NewsRewrite::MOOD_POSITIVE,
                    NewsRewrite::MOOD_SAD,
                ]),
        );
});

test('an unknown news article returns not found', function () {
    $this->get(route('news.show', 999999))->assertNotFound();
});
