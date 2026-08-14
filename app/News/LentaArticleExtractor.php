<?php

namespace App\News;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LentaArticleExtractor
{
    public function extract(string $url): ArticleContentData
    {
        $this->ensureLentaUrl($url);

        $response = Http::accept('text/html')
            ->withUserAgent('Mozilla/5.0 (compatible; NewsGrid/1.0)')
            ->withCookies(['unity_pause_sso' => '1'], 'lenta.ru')
            ->connectTimeout(10)
            ->timeout(30)
            ->retry([500, 1000])
            ->get($url)
            ->throw();

        $document = HTMLDocument::createFromString(
            $response->body(),
            LIBXML_NOERROR,
        );

        $leadElement = $document->querySelector('.topic-body__title-yandex');
        $lead = $leadElement === null ? null : trim($leadElement->textContent);

        if ($lead === '') {
            $lead = null;
        }

        $paragraphs = [];

        foreach (
            $document->querySelectorAll(
                '.topic-body__content > p.topic-body__content-text',
            ) as $paragraph
        ) {
            $text = trim($paragraph->textContent ?? '');

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        if ($paragraphs === []) {
            throw new RuntimeException(
                "Lenta article content could not be extracted from {$url}.",
            );
        }

        return new ArticleContentData(
            lead: $lead,
            content: implode("\n\n", $paragraphs),
        );
    }

    private function ensureLentaUrl(string $url): void
    {
        if (
            parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_HOST) !== 'lenta.ru'
        ) {
            throw new RuntimeException('Unexpected Lenta article URL.');
        }
    }
}
