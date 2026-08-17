<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsRewrite>
 */
class NewsRewriteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'news_id' => News::factory(),
            'mood' => fake()->randomElement(NewsRewrite::MOODS),
            'status' => NewsRewrite::STATUS_PENDING,
        ];
    }
}
