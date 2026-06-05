<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `internal_states` (DATABASE.md §4.5, ADR 0014).
 *
 * A character's private `[SELF]` for a save: derived mood, optional mood pin,
 * motivation, and masks. Unique per `(session_id, character_id)`; the live
 * feelings hang off it in `active_emotions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('mood', 120)->nullable();
            $table->string('mood_override', 120)->nullable();
            $table->json('motivation')->nullable();
            $table->json('masks')->nullable();
            $table->timestamp('last_clocked_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_states');
    }
};
