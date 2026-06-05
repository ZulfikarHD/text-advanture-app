<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `character_cards` (DATABASE.md §3.3, ADR 0001/0013).
 *
 * Per-(character, chapter) compiled, spoiler-free snapshot. `(character_id,
 * chapter_id)` is unique so a character has exactly one card per chapter.
 *
 * `review_item_id` references the save-realm `review_items` table that lands in
 * Sprint 4 (S-4.2.1); the column is added now (nullable) but its FK constraint
 * is intentionally deferred until that table exists (see PH-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->longText('folded_identity');
            $table->json('knowledge_boundary');
            $table->json('disposition_priors');
            $table->json('voice');
            $table->json('tells');
            $table->text('appearance')->nullable();
            $table->string('compiled_source_hash', 64)->nullable();
            // FK constraint deferred to Sprint 4 (review_items not yet created).
            $table->foreignId('review_item_id')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_cards');
    }
};
