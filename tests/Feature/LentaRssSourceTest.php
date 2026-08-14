<?php

use App\News\LentaRssSource;
use Illuminate\Support\Facades\Http;

test('it reads and normalizes Lenta RSS', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://lenta.ru/rss/news' => Http::response(
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <guid>https://lenta.ru/news/2026/08/13/example/</guid>
                        <author>Иван Иванов</author>
                        <title>Тестовая новость</title>
                        <link>https://lenta.ru/news/2026/08/13/example/</link>
                        <pubDate>Thu, 13 Aug 2026 14:10:22 +0300</pubDate>
                        <enclosure
                            url="https://icdn.lenta.ru/example.jpg"
                            type="image/jpeg"
                        />
                        <category>Россия</category>
                    </item>
                </channel>
            </rss>
            XML,
            headers: ['Content-Type' => 'application/rss+xml'],
        ),
    ]);

    $newsItems = (new LentaRssSource)->fetch();

    expect($newsItems)->toHaveCount(1)
        ->and($newsItems[0]->guid)
        ->toBe('https://lenta.ru/news/2026/08/13/example/')
        ->and($newsItems[0]->title)
        ->toBe('Тестовая новость')
        ->and($newsItems[0]->author)
        ->toBe('Иван Иванов')
        ->and($newsItems[0]->category)
        ->toBe('Россия')
        ->and($newsItems[0]->imageUrl)
        ->toBe('https://icdn.lenta.ru/example.jpg')
        ->and($newsItems[0]->publishedAt->toIso8601String())
        ->toBe('2026-08-13T14:10:22+03:00');

    Http::assertSentCount(1);
});

test('it rejects invalid RSS', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://lenta.ru/rss/news' => Http::response(
            '<invalid>',
            headers: ['Content-Type' => 'application/rss+xml'],
        ),
    ]);

    expect(fn () => (new LentaRssSource)->fetch())
        ->toThrow(RuntimeException::class, 'Lenta returned invalid RSS.');
});
