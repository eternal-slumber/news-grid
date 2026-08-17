<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Support\Facades\Queue;

#[Signature('news:clear {--force : Delete without confirmation}')]
#[Description('Delete all news, rewrites, and queued jobs')]
final class ClearNewsCommand extends Command
{
    public function handle(): int
    {
        $connectionName = (string) config('queue.default');
        $queueName = (string) config("queue.connections.{$connectionName}.queue", 'default');
        $queue = Queue::connection($connectionName);

        if (! $queue instanceof ClearableQueue) {
            $this->error("The [{$connectionName}] queue cannot be cleared.");

            return self::FAILURE;
        }

        $newsCount = News::query()->count();
        $rewriteCount = NewsRewrite::query()->count();
        $queuedCount = $queue->size($queueName);

        if ($newsCount === 0 && $queuedCount === 0) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        if (
            ! $this->option('force')
            && ! $this->confirm(
                "Delete {$newsCount} news, {$rewriteCount} rewrites, and {$queuedCount} queued jobs?",
            )
        ) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $clearedJobs = $queue->clear($queueName);
        $deletedNews = News::query()->delete();

        $this->info(
            "Deleted: {$deletedNews} news, {$rewriteCount} rewrites; cleared jobs: {$clearedJobs}.",
        );

        return self::SUCCESS;
    }
}
