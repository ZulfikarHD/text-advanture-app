<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global library: `character_archetypes` (DATABASE.md §3.13, ADR 0018).
 *
 * App-wide seedable whole-character shapes (e.g. koakuma) carrying base
 * opacity, suggested live axes, default priors/registers/sensitivities, and a
 * voice scaffold. A starting point for character creation, never a constraint;
 * carries no `story_id`. Seeded in Sprint 6 (S-6.1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('base_opacity');
            $table->json('suggested_live_axes');
            $table->json('default_disposition_priors');
            $table->json('default_registers');
            $table->json('default_sensitivities');
            $table->json('voice_scaffold')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_archetypes');
    }
};
