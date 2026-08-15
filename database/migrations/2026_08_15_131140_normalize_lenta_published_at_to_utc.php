<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $newsItems = DB::table('news')
            ->where('source_name', 'Lenta.ru')
            ->get(['id', 'published_at']);

        foreach ($newsItems as $newsItem) {
            DB::table('news')
                ->where('id', $newsItem->id)
                ->update([
                    'published_at' => CarbonImmutable::parse(
                        $newsItem->published_at,
                        'UTC',
                    )->subHours(3),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Correct UTC timestamps must not be shifted back.
    }
};
