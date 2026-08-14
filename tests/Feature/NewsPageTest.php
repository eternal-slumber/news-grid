<?php

use App\Models\News;
use Inertia\Testing\AssertableInertia as Assert;

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
