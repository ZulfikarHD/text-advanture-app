<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `reveal_ledger` (DATABASE.md §3.4, ADR 0013 §3).
 *
 * Maps load-bearing secrets to the chapter they become known, driving the card
 * compile clamp (a fact revealed after chapter N becomes an explicit
 * `does_not_know` on a chapter-N card). A null `character_id` means a world
 * secret rather than a per-character one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reveal_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()
                ->constrained('characters')->nullOnDelete();
            $table->string('fact', 255);
            $table->foreignId('reveal_chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->json('who_knows');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reveal_ledger');
    }
};
