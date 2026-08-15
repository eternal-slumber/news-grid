<?php

use App\Models\News;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('it stores new articles once', function () {
    Http::preventStrayRequests();

    $rssItems = collect(range(1, 10))->map(function (int $number): string {
        return <<<XML
            <item>
                <guid>https://lenta.ru/news/2026/08/13/article-{$number}/</guid>
                <author>Автор {$number}</author>
                <title>Новость {$number}</title>
                <link>https://lenta.ru/news/2026/08/13/article-{$number}/</link>
                <pubDate>Thu, 13 Aug 2026 14:10:22 +0300</pubDate>
                <enclosure url="https://icdn.lenta.ru/article-{$number}.jpg" type="image/jpeg" />
                <category>Россия</category>
            </item>
            XML;
    })->implode("\n");

    Http::fake([
        'https://lenta.ru/rss/news' => Http::response(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    {$rssItems}
                </channel>
            </rss>
            XML),
        'https://lenta.ru/news/*' => Http::response(<<<'HTML'
            <!doctype html>
            <html lang="ru">
                <body>
                    <div class="topic-body__title-yandex">Краткий анонс</div>
                    <div class="topic-body__content">
                        <p class="topic-body__content-text">Первый абзац.</p>
                        <p class="topic-body__content-text">Второй абзац.</p>
                    </div>
                </body>
            </html>
            HTML),
    ]);

    $this->artisan('news:fetch')
        ->expectsOutput('Saved: 10; skipped: 0; failed: 0.')
        ->assertSuccessful();

    expect(News::query()->count())->toBe(10);

    $news = News::query()->orderBy('id')->firstOrFail();

    expect($news->external_id)->toBe('https://lenta.ru/news/2026/08/13/article-1/')
        ->and($news->source_name)->toBe('Lenta.ru')
        ->and($news->title)->toBe('Новость 1')
        ->and($news->lead)->toBe('Краткий анонс')
        ->and($news->content)->toBe("Первый абзац.\n\nВторой абзац.")
        ->and($news->published_at->toIso8601String())
        ->toBe('2026-08-13T11:10:22+00:00');

    $this->artisan('news:fetch')
        ->expectsOutput('Saved: 0; skipped: 10; failed: 0.')
        ->assertSuccessful();

    expect(News::query()->count())->toBe(10)
        ->and(Http::recorded(
            fn (Request $request): bool => $request->url() !== 'https://lenta.ru/rss/news',
        ))->toHaveCount(10);

    Http::assertSentCount(12);
});

test('it continues after an article cannot be extracted', function () {
    Http::preventStrayRequests();
    Log::spy();

    Http::fake([
        'https://lenta.ru/rss/news' => Http::response(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <item>
                        <guid>failed-article</guid>
                        <title>Сломанная новость</title>
                        <link>https://lenta.ru/news/2026/08/13/failed/</link>
                        <pubDate>Thu, 13 Aug 2026 14:10:22 +0300</pubDate>
                        <enclosure url="https://icdn.lenta.ru/failed.jpg" type="image/jpeg" />
                    </item>
                    <item>
                        <guid>valid-article</guid>
                        <title>Рабочая новость</title>
                        <link>https://lenta.ru/news/2026/08/13/valid/</link>
                        <pubDate>Thu, 13 Aug 2026 14:11:22 +0300</pubDate>
                        <enclosure url="https://icdn.lenta.ru/valid.jpg" type="image/jpeg" />
                    </item>
                </channel>
            </rss>
            XML),
        'https://lenta.ru/news/2026/08/13/failed/' => Http::response('<html></html>'),
        'https://lenta.ru/news/2026/08/13/valid/' => Http::response(<<<'HTML'
            <div class="topic-body__title-yandex">Краткий анонс</div>
            <div class="topic-body__content">
                <p class="topic-body__content-text">Полный текст.</p>
            </div>
            HTML),
    ]);

    $this->artisan('news:fetch')
        ->expectsOutput('Failed to import https://lenta.ru/news/2026/08/13/failed/. See log for details.')
        ->expectsOutput('Saved: 1; skipped: 0; failed: 1.')
        ->assertFailed();

    expect(News::query()->sole()->external_id)->toBe('valid-article');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Lenta article could not be imported.'
            && $context['url'] === 'https://lenta.ru/news/2026/08/13/failed/'
            && str_contains($context['reason'], 'could not be extracted'));
});
