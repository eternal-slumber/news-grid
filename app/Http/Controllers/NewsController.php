<?php

namespace App\Http\Controllers;

use App\Models\News;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /**
     * Show the latest fetched news.
     */
    public function __invoke(): Response
    {
        return Inertia::render('news/index', [
            'news' => News::query()
                ->select([
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
                ])
                ->latest('published_at')
                ->limit(30)
                ->get(),
        ]);
    }
}
