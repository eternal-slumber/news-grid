<?php

namespace App\News;

use Carbon\CarbonImmutable;

final readonly class NewsItemData
{
    public function __construct(
        public string $guid,
        public string $title,
        public string $link,
        public ?string $author,
        public ?string $category,
        public ?string $imageUrl,
        public CarbonImmutable $publishedAt,
    ) {}
}
