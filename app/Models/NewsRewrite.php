<?php

namespace App\Models;

use Database\Factories\NewsRewriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $news_id
 * @property string $mood
 * @property string $status
 * @property string|null $title
 * @property string|null $lead
 * @property string|null $content
 * @property bool|null $validation_passed
 * @property string|null $validation_message
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read News $news
 */
#[Fillable([
    'news_id',
    'mood',
    'status',
    'title',
    'lead',
    'content',
    'validation_passed',
    'validation_message',
    'error_message',
])]
class NewsRewrite extends Model
{
    /** @use HasFactory<NewsRewriteFactory> */
    use HasFactory;

    public const string MOOD_POSITIVE = 'positive';

    public const string MOOD_SAD = 'sad';

    public const string MOOD_IRONIC = 'ironic';

    public const array MOODS = [
        self::MOOD_POSITIVE,
        self::MOOD_SAD,
        self::MOOD_IRONIC,
    ];

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return BelongsTo<News, $this>
     */
    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validation_passed' => 'boolean',
        ];
    }
}
