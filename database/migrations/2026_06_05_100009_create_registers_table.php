<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `registers` (DATABASE.md §3.6, ADR 0006).
 *
 * A character's conversational registers, either instantiated from a shared
 * archetype or bespoke. `(character_id, slug)` is unique.
 *
 * `archetype_id` references the global `register_archetypes` library seeded in
 * Sprint 4 (S-4.1.2); the column is added now (nullable) but its FK constraint
 * is intentionally deferred until that library exists (see PH-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            // FK constraint deferred to Sprint 4 (register_archetypes not yet created).
            $table->foreignId('archetype_id')->nullable();
            $table->json('dimensions');
            $table->string('speech_ref', 120)->nullable();
            $table->json('tells');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
