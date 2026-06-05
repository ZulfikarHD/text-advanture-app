<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `beats` (DATABASE.md §3.12, ADR 0015).
 *
 * Ordered beats within a scene. `intent` is omniscient author-side text that is
 * never injected raw; `nudge_target_character_id` optionally frames a nudge onto
 * a character. `(scene_id, number)` is unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->integer('number');
            $table->text('intent');
            $table->string('goal', 255);
            $table->integer('word_budget');
            $table->foreignId('nudge_target_character_id')->nullable()
                ->constrained('characters')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scene_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beats');
    }
};
