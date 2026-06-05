<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global library: `register_archetypes` (DATABASE.md §3.5, ADR 0006).
 *
 * App-wide, story-independent grammar skeletons (e.g. one-way-mirror,
 * romantic-deflection) reused across stories. Carries no `story_id`. A
 * `registers.archetype_id` FK binds to this table once it exists (PH-16,
 * resolved this sprint). Seeded in Sprint 6 (S-6.1.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->json('dimensions');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_archetypes');
    }
};
