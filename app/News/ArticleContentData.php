<?php

namespace App\News;

final readonly class ArticleContentData
{
    public function __construct(
        public ?string $lead,
        public string $content,
    ) {}
}
