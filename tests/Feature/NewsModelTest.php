<?php

use App\Models\News;
use Illuminate\Database\QueryException;

test('news can be stored', function () {
    $news = News::factory()->create();

    $this->assertModelExists($news);

    expect($news->published_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($news->fetched_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('source and external id must be unique together', function () {
    News::factory()->create([
        'source_name' => 'Lenta.ru',
        'external_id' => 'lenta-guid-1',
    ]);

    News::factory()->create([
        'source_name' => 'Another source',
        'external_id' => 'lenta-guid-1',
    ]);

    expect(fn () => News::factory()->create([
        'source_name' => 'Lenta.ru',
        'external_id' => 'lenta-guid-1',
    ]))->toThrow(QueryException::class);
});
