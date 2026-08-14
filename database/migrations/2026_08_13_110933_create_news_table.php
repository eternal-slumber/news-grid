<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('source_name');
            $table->text('original_url');
            $table->string('author')->nullable();
            $table->string('category')->nullable();
            $table->text('title');
            $table->text('lead')->nullable();
            $table->longText('content');
            $table->text('image_url')->nullable();
            $table->timestampTz('published_at');
            $table->timestampTz('fetched_at');

            $table->unique(['source_name', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
