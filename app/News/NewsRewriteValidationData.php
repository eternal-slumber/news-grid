<?php

namespace App\News;

final readonly class NewsRewriteValidationData
{
    public function __construct(
        public bool $passed,
        public string $message,
    ) {}
}
