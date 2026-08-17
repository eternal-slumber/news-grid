<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsRewrite;
use Illuminate\Database\Eloquent\Relations\Relation;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /**
     * Show the latest fetched news.
     */
    public function index(): Response
    {
        return Inertia::render('news/index', [
            'news' => News::query()
                ->with([
                    'rewrites' => fn (Relation $query): Relation => $query
                        ->select(['id', 'news_id', 'mood', 'status', 'title', 'lead', 'validation_passed'])
                        ->where('status', NewsRewrite::STATUS_COMPLETED)
                        ->where('validation_passed', true),
                ])
                ->select([
                    'id',
                    'source_name',
                    'author',
                    'category',
                    'title',
                    'lead',
                    'image_url',
                    'published_at',
                ])
                ->latest('published_at')
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * Show one news article and its emotional versions.
     */
    public function show(News $news): Response
    {
        $news->load([
            'rewrites' => fn (Relation $query): Relation => $query
                ->select(['id', 'news_id', 'mood', 'status', 'title', 'lead', 'content', 'validation_passed', 'validation_message'])
                ->where('status', NewsRewrite::STATUS_COMPLETED)
                ->where('validation_passed', true),
        ]);

        return Inertia::render('news/show', [
            'news' => [
                ...$news->only([
                    'id',
                    'source_name',
                    'original_url',
                    'author',
                    'category',
                    'title',
                    'lead',
                    'content',
                    'image_url',
                    'published_at',
                ]),
                'rewrites' => $news->rewrites,
            ],
        ]);
    }
}
