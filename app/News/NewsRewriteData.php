<?php

namespace App\News;

final readonly class NewsRewriteData
{
    public function __construct(
        public string $title,
        public ?string $lead,
        public string $content,
    ) {}
}
