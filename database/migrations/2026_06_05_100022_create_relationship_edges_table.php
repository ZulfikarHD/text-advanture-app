<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `relationship_edges` (DATABASE.md §4.2, ADR 0002).
 *
 * A directed `from -> to` relationship owned by a save. `A->B` is distinct from
 * `B->A`; unique per `(session_id, from_character_id, to_character_id)`. The
 * per-axis state hangs off this row in `edge_axes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('from_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('to_character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('register_base', 120);
            $table->json('register_overrides')->nullable();
            $table->json('topic_flags')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'from_character_id', 'to_character_id'], 'rel_edge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_edges');
    }
};
