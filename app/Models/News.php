<?php

namespace App\Models;

use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $external_id
 * @property string $source_name
 * @property string $original_url
 * @property string|null $author
 * @property string|null $category
 * @property string $title
 * @property string|null $lead
 * @property string $content
 * @property string|null $image_url
 * @property Carbon $published_at
 * @property Carbon $fetched_at
 */
#[Fillable([
    'external_id',
    'source_name',
    'original_url',
    'author',
    'category',
    'title',
    'lead',
    'content',
    'image_url',
    'published_at',
    'fetched_at',
])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return HasMany<NewsRewrite, $this>
     */
    public function rewrites(): HasMany
    {
        return $this->hasMany(NewsRewrite::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }
}
