<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `lorebook_entries` (DATABASE.md §3.9, ADR 0013 §5).
 *
 * Keyword-injected world facts (world only, never a character's interiority).
 * `min_reveal_chapter_id` optionally withholds an entry until a given chapter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lorebook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->json('keywords');
            $table->text('content');
            $table->foreignId('min_reveal_chapter_id')->nullable()
                ->constrained('chapters')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lorebook_entries');
    }
};
