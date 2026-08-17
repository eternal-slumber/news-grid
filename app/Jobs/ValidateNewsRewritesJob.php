<?php

namespace App\Jobs;

use App\Models\News;
use App\Models\NewsRewrite;
use App\News\NewsRewriteData;
use App\News\NewsRewriteValidationData;
use App\News\OpenRouterClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ValidateNewsRewritesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 7200;

    public int $uniqueFor = 7500;

    public function __construct(public readonly int $newsId) {}

    public function handle(OpenRouterClient $openRouter): void
    {
        $news = News::query()
            ->with('rewrites')
            ->findOrFail($this->newsId);
        $models = $news->rewrites
            ->where('status', NewsRewrite::STATUS_PROCESSING)
            ->whereIn('mood', NewsRewrite::MOODS)
            ->keyBy('mood');

        if ($models->isEmpty()) {
            return;
        }

        $rewrites = $models->map(
            fn (NewsRewrite $rewrite): NewsRewriteData => $this->rewriteData($rewrite),
        )->all();

        try {
            $validations = $openRouter->validate(
                $news,
                $rewrites,
                $this->validationWarnings($news, $rewrites),
            );

            foreach ($validations as $mood => $validation) {
                $rewriteData = $rewrites[$mood];

                for ($attempt = 0; ! $validation->passed && $attempt < 2; $attempt++) {
                    $rewriteData = $openRouter->repair(
                        $news,
                        $mood,
                        $rewriteData,
                        $validation,
                    );

                    $validation = $openRouter->validate(
                        $news,
                        [$mood => $rewriteData],
                        $this->validationWarnings($news, [$mood => $rewriteData]),
                    )[$mood];
                }

                $this->storeValidation($models[$mood], $rewriteData, $validation);
            }
        } catch (Throwable $exception) {
            NewsRewrite::query()
                ->where('news_id', $this->newsId)
                ->where('status', NewsRewrite::STATUS_PROCESSING)
                ->update([
                    'error_message' => Str::limit($exception->getMessage(), 2000),
                ]);

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
        return "validate:{$this->newsId}";
    }

    public function failed(?Throwable $exception): void
    {
        NewsRewrite::query()
            ->where('news_id', $this->newsId)
            ->where('status', NewsRewrite::STATUS_PROCESSING)
            ->update([
                'status' => NewsRewrite::STATUS_FAILED,
                'error_message' => Str::limit(
                    $exception?->getMessage() ?? 'Rewrite validation job failed.',
                    2000,
                ),
            ]);
    }

    private function rewriteData(NewsRewrite $rewrite): NewsRewriteData
    {
        if ($rewrite->title === null || $rewrite->content === null) {
            throw new RuntimeException("Rewrite {$rewrite->id} has no generated content.");
        }

        return new NewsRewriteData(
            title: $rewrite->title,
            lead: $rewrite->lead,
            content: $rewrite->content,
        );
    }

    /**
     * @param  array<string, NewsRewriteData>  $rewrites
     * @return array<string, list<string>>
     */
    private function validationWarnings(News $news, array $rewrites): array
    {
        $originalNumbers = $this->numbers(
            "{$news->title}\n{$news->lead}\n{$news->content}",
        );
        $warnings = [];

        foreach ($rewrites as $mood => $rewrite) {
            $rewriteNumbers = $this->numbers(
                "{$rewrite->title}\n{$rewrite->lead}\n{$rewrite->content}",
            );
            $newNumbers = array_values(array_diff($rewriteNumbers, $originalNumbers));

            if ($newNumbers !== []) {
                $warnings[$mood][] = 'В рерайте появились новые числа: '.implode(', ', $newNumbers);
            }

            $duplicateMoods = collect($rewrites)
                ->except($mood)
                ->filter(
                    fn (NewsRewriteData $candidate): bool => Str::squish($candidate->content)
                        === Str::squish($rewrite->content),
                )
                ->keys()
                ->all();

            if ($duplicateMoods !== []) {
                $warnings[$mood][] = 'Текст полностью совпадает с версиями: '.implode(', ', $duplicateMoods);
            }
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function numbers(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    private function storeValidation(
        NewsRewrite $rewrite,
        NewsRewriteData $rewriteData,
        NewsRewriteValidationData $validation,
    ): void {
        $rewrite->update([
            'status' => $validation->passed
                ? NewsRewrite::STATUS_COMPLETED
                : NewsRewrite::STATUS_FAILED,
            'title' => $rewriteData->title,
            'lead' => $rewriteData->lead,
            'content' => $rewriteData->content,
            'validation_passed' => $validation->passed,
            'validation_message' => $validation->message,
            'error_message' => null,
        ]);
    }
}
