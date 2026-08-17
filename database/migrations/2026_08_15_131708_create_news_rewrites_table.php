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
        Schema::create('news_rewrites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->string('mood', 32);
            $table->string('status', 32)->default('pending');
            $table->text('title')->nullable();
            $table->text('lead')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('validation_passed')->nullable();
            $table->text('validation_message')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->unique(['news_id', 'mood']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_rewrites');
    }
};
