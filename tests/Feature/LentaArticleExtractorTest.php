<?php

use App\News\LentaArticleExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it extracts the lead and article paragraphs', function () {
    Http::preventStrayRequests();

    $url = 'https://lenta.ru/news/2026/08/13/example/';

    Http::fake([
        $url => Http::response(<<<'HTML'
            <!doctype html>
            <html lang="ru">
                <body>
                    <div class="topic-body__title-yandex">Краткий анонс</div>
                    <div class="topic-body__content">
                        <p class="topic-body__content-text">Первый <strong>абзац</strong>.</p>
                        <p class="topic-body__content-text"></p>
                        <p class="topic-body__content-text">Второй абзац.</p>
                    </div>
                </body>
            </html>
            HTML),
    ]);

    $article = (new LentaArticleExtractor)->extract($url);

    expect($article->lead)->toBe('Краткий анонс')
        ->and($article->content)->toBe("Первый абзац.\n\nВторой абзац.");

    Http::assertSent(fn (Request $request): bool => $request->url() === $url
        && $request->hasHeader(
            'User-Agent',
            'Mozilla/5.0 (compatible; NewsGrid/1.0)',
        ));
});

test('it rejects non-Lenta URLs without making a request', function () {
    Http::preventStrayRequests();

    expect(fn () => (new LentaArticleExtractor)->extract(
        'https://example.com/news/article/',
    ))->toThrow(RuntimeException::class, 'Unexpected Lenta article URL.');

    Http::assertNothingSent();
});

test('it extracts an article without an optional lead', function () {
    Http::preventStrayRequests();

    $url = 'https://lenta.ru/news/2026/08/13/without-lead/';

    Http::fake([
        $url => Http::response(<<<'HTML'
            <div class="topic-body__content">
                <p class="topic-body__content-text">Основной текст статьи.</p>
            </div>
            HTML),
    ]);

    $article = (new LentaArticleExtractor)->extract($url);

    expect($article->lead)->toBeNull()
        ->and($article->content)->toBe('Основной текст статьи.');
});

test('it rejects an article without the expected content', function () {
    Http::preventStrayRequests();

    $url = 'https://lenta.ru/news/2026/08/13/missing-content/';

    Http::fake([
        $url => Http::response('<html><body>Article unavailable</body></html>'),
    ]);

    expect(fn () => (new LentaArticleExtractor)->extract($url))
        ->toThrow(
            RuntimeException::class,
            "Lenta article content could not be extracted from {$url}.",
        );
});
