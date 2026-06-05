<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `chapter_logs` (DATABASE.md §4.14, ADR 0016).
 *
 * Per-chapter continuity rollup for a save: an optional summary plus the key
 * beat events. Carries only `created_at` (the summary may be filled in later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('events');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_logs');
    }
};
