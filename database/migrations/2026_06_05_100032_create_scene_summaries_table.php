<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `scene_summaries` (DATABASE.md §4.13, ADR 0015/0016).
 *
 * Context-memory: a scene compressed at SCENE_DONE, with flags marking whether
 * batched drift and decay were applied. Carries only `created_at` (the applied
 * flags may be toggled, so it is not append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary');
            $table->boolean('drift_applied')->default(false);
            $table->boolean('decay_applied')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_summaries');
    }
};
