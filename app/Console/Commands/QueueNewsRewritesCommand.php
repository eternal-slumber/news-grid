<?php

namespace App\Console\Commands;

use App\Jobs\GenerateNewsRewritesJob;
use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:rewrite {news? : News ID} {--latest= : Queue the latest N news} {--force : Requeue completed and failed rewrites}')]
#[Description('Queue mood rewrites for a news article')]
final class QueueNewsRewritesCommand extends Command
{
    public function handle(): int
    {
        $newsArgument = $this->argument('news');
        $latestOption = $this->option('latest');

        if (($newsArgument === null) === ($latestOption === null)) {
            $this->error('Provide either a News ID or --latest, but not both.');

            return self::FAILURE;
        }

        if ($latestOption !== null) {
            $latestCount = $this->positiveInteger($latestOption);

            if ($latestCount === null || $latestCount > 100) {
                $this->error('--latest must be an integer between 1 and 100.');

                return self::FAILURE;
            }

            $newsItems = News::query()
                ->latest('published_at')
                ->limit($latestCount)
                ->get(['id']);
        } else {
            $newsId = $this->positiveInteger($newsArgument);

            if ($newsId === null) {
                $this->error('News ID must be a positive integer.');

                return self::FAILURE;
            }

            $news = News::query()->find($newsId);

            if ($news === null) {
                $this->error("News {$newsId} was not found.");

                return self::FAILURE;
            }

            $newsItems = collect([$news]);
        }

        if ($newsItems->isEmpty()) {
            $this->error('No news were found.');

            return self::FAILURE;
        }

        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($newsItems as $news) {
            $rewrites = collect(NewsRewrite::MOODS)
                ->map(fn (string $mood): NewsRewrite => NewsRewrite::query()->firstOrCreate([
                    'news_id' => $news->id,
                    'mood' => $mood,
                ]));
            $hasNewRewrite = $rewrites->contains->wasRecentlyCreated;
            $hasActiveRewrite = $rewrites->contains(
                fn (NewsRewrite $rewrite): bool => ! $rewrite->wasRecentlyCreated
                    && in_array($rewrite->status, [
                        NewsRewrite::STATUS_PENDING,
                        NewsRewrite::STATUS_PROCESSING,
                    ], true),
            );
            $allCompleted = $rewrites->every(
                fn (NewsRewrite $rewrite): bool => $rewrite->status === NewsRewrite::STATUS_COMPLETED,
            );

            if ($hasActiveRewrite || (! $hasNewRewrite && $allCompleted && ! $this->option('force'))) {
                $skippedCount++;

                continue;
            }

            $rewrites->each(fn (NewsRewrite $rewrite) => $rewrite->update([
                'status' => NewsRewrite::STATUS_PENDING,
                'title' => null,
                'lead' => null,
                'content' => null,
                'validation_passed' => null,
                'validation_message' => null,
                'error_message' => null,
            ]));

            GenerateNewsRewritesJob::dispatch($news->id);
            $queuedCount++;
        }

        $this->info("Queued: {$queuedCount}; skipped: {$skippedCount}.");

        return self::SUCCESS;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return $integer === false ? null : $integer;
    }
}
