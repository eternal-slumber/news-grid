<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->uuid(),
            'source_name' => 'Lenta.ru',
            'original_url' => fake()->unique()->url(),
            'author' => fake()->name(),
            'category' => fake()->word(),
            'title' => fake()->sentence(),
            'lead' => fake()->paragraph(),
            'content' => fake()->paragraphs(5, true),
            'image_url' => fake()->url(),
            'published_at' => fake()->dateTimeBetween('-1 week'),
            'fetched_at' => now(),
        ];
    }
}
