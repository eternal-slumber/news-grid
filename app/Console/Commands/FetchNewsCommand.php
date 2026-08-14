<?php

namespace App\Console\Commands;

use App\Models\News;
use App\News\LentaArticleExtractor;
use App\News\LentaRssSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('news:fetch')]
#[Description('Fetch new articles from Lenta.ru')]
final class FetchNewsCommand extends Command
{
    private const int MAX_ARTICLES = 10;

    public function __construct(
        private readonly LentaRssSource $rssSource,
        private readonly LentaArticleExtractor $articleExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $newsItems = $this->rssSource->fetch();

        $existingExternalIds = News::query()
            ->where('source_name', LentaRssSource::SOURCE_NAME)
            ->whereIn('external_id', collect($newsItems)->pluck('guid'))
            ->pluck('external_id')
            ->all();

        $savedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($newsItems as $newsItem) {
            if ($savedCount >= self::MAX_ARTICLES) {
                break;
            }

            if (in_array($newsItem->guid, $existingExternalIds, true)) {
                $skippedCount++;

                continue;
            }

            try {
                $articleContent = $this->articleExtractor->extract($newsItem->link);

                News::query()->create([
                    'external_id' => $newsItem->guid,
                    'source_name' => LentaRssSource::SOURCE_NAME,
                    'original_url' => $newsItem->link,
                    'author' => $newsItem->author,
                    'category' => $newsItem->category,
                    'title' => $newsItem->title,
                    'lead' => $articleContent->lead,
                    'content' => $articleContent->content,
                    'image_url' => $newsItem->imageUrl,
                    'published_at' => $newsItem->publishedAt,
                    'fetched_at' => now(),
                ]);
            } catch (Throwable $exception) {
                $failedCount++;

                Log::warning('Lenta article could not be imported.', [
                    'url' => $newsItem->link,
                    'reason' => $exception->getMessage(),
                    'exception' => $exception,
                ]);

                $this->warn("Failed to import {$newsItem->link}. See log for details.");

                continue;
            }

            $existingExternalIds[] = $newsItem->guid;
            $savedCount++;
        }

        $this->info(
            "Saved: {$savedCount}; skipped: {$skippedCount}; failed: {$failedCount}.",
        );

        return $failedCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
