<?php

namespace App\Jobs;

use App\Models\News;
use App\Models\NewsRewrite;
use App\News\OpenRouterClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class GenerateNewsRewritesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $newsId) {}

    public function handle(OpenRouterClient $openRouter): void
    {
        $news = News::query()->findOrFail($this->newsId);
        $rewrites = collect(NewsRewrite::MOODS)
            ->mapWithKeys(fn (string $mood): array => [
                $mood => NewsRewrite::query()->firstOrCreate([
                    'news_id' => $news->id,
                    'mood' => $mood,
                ]),
            ]);

        if ($rewrites->every(
            fn (NewsRewrite $rewrite): bool => $rewrite->status === NewsRewrite::STATUS_COMPLETED,
        )) {
            return;
        }

        $rewrites->each(fn (NewsRewrite $rewrite) => $rewrite->update([
            'status' => NewsRewrite::STATUS_PROCESSING,
            'error_message' => null,
        ]));

        try {
            foreach ($openRouter->rewriteAll($news) as $mood => $rewriteData) {
                $rewrites[$mood]->update([
                    'title' => $rewriteData->title,
                    'lead' => $rewriteData->lead,
                    'content' => $rewriteData->content,
                    'validation_passed' => null,
                    'validation_message' => null,
                ]);
            }

            ValidateNewsRewritesJob::dispatch($news->id);
        } catch (Throwable $exception) {
            $this->recordRetryableFailure($exception);

            if (Str::contains($exception->getMessage(), 'free-models-per-day')) {
                $this->failed($exception);
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return "generate:{$this->newsId}";
    }

    public function failed(?Throwable $exception): void
    {
        NewsRewrite::query()
            ->where('news_id', $this->newsId)
            ->whereIn('mood', NewsRewrite::MOODS)
            ->update([
                'status' => NewsRewrite::STATUS_FAILED,
                'error_message' => Str::limit(
                    $exception?->getMessage() ?? 'Rewrite generation job failed.',
                    2000,
                ),
            ]);
    }

    private function recordRetryableFailure(Throwable $exception): void
    {
        NewsRewrite::query()
            ->where('news_id', $this->newsId)
            ->whereIn('mood', NewsRewrite::MOODS)
            ->update([
                'status' => NewsRewrite::STATUS_PENDING,
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);
    }
}
