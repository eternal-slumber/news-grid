<?php

namespace App\News;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

final class LentaRssSource
{
    public const SOURCE_NAME = 'Lenta.ru';

    private const RSS_URL = 'https://lenta.ru/rss/news';

    /**
     * @return list<NewsItemData>
     */
    public function fetch(): array
    {
        $response = Http::accept('application/rss+xml')
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([250, 750])
            ->get(self::RSS_URL)
            ->throw();

        $rss = $this->parseXml($response->body());

        $newsItems = [];

        foreach ($rss->channel->item as $item) {
            $newsItems[] = $this->mapItem($item);
        }

        return $newsItems;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $rss = simplexml_load_string(
                $xml,
                SimpleXMLElement::class,
                LIBXML_NOCDATA | LIBXML_NONET
            );

            if ($rss === false || ! isset($rss->channel)) {
                throw new RuntimeException('Lenta returned invalid RSS.');
            }

            return $rss;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function mapItem(SimpleXMLElement $item): NewsItemData
    {
        $link = $this->requiredText($item->link, 'link');

        $this->ensureLentaUrl($link);

        return new NewsItemData(
            guid: $this->requiredText($item->guid, 'guid'),
            title: $this->requiredText($item->title, 'title'),
            link: $link,
            author: $this->optionalText($item->author),
            category: $this->optionalText($item->category),
            imageUrl: $this->optionalText($item->enclosure['url']),
            publishedAt: CarbonImmutable::parse(
                $this->requiredText($item->pubDate, 'pubDate'),
            )->utc(),
        );
    }

    private function requiredText(SimpleXMLElement $element, string $field): string
    {
        $value = trim((string) $element);

        if ($value === '') {
            throw new RuntimeException(
                "Lenta RSS item id is missing {$field}"
            );
        }

        return $value;
    }

    private function optionalText(SimpleXMLElement $element): ?string
    {
        $value = trim((string) $element);

        return $value !== '' ? $value : null;
    }

    private function ensureLentaUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme !== 'https' || $host !== 'lenta.ru') {
            throw new RuntimeException(
                'Lenta RSS item contains an unexpected article',
            );
        }
    }
}
