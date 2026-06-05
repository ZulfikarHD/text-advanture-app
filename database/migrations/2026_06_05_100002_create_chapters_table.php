<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `chapters` (DATABASE.md §3.10, ADR 0015).
 *
 * Ordered chapters within a story. `(story_id, number)` is unique so chapter
 * numbering is stable per story.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->integer('number');
            $table->string('title', 200);
            $table->string('pov_default', 120);
            $table->text('outline')->nullable();
            $table->integer('word_cap')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
