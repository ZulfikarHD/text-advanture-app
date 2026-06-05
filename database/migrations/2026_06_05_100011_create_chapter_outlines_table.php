<?php

use App\Enums\OutlineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `chapter_outlines` (DATABASE.md §3.15, ADR 0019).
 *
 * The author's raw outline text (never injected at runtime) plus the linkage to
 * the chapter it compiles into. `chapter_id` is set once compiled; an outline
 * may span chapters.
 *
 * `review_item_id` references the save-realm `review_items` table that lands in
 * Sprint 4 (S-4.2.1); the column is added now (nullable) but its FK constraint
 * is intentionally deferred until that table exists (see PH-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_outlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->nullable()
                ->constrained('chapters')->nullOnDelete();
            $table->longText('raw_text');
            $table->enum('status', array_column(OutlineStatus::cases(), 'value'));
            // FK constraint deferred to Sprint 4 (review_items not yet created).
            $table->foreignId('review_item_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_outlines');
    }
};
