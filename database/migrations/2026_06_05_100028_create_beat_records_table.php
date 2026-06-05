<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `beat_records` (DATABASE.md §4.8, ADR 0010) - APPEND-ONLY.
 *
 * The public, observable layer of a played beat: behavior, dialogue, and
 * hedged perceived reads. This is the ONLY cross-agent layer - private feeling
 * lives in the separate `beat_true_states` child so a "read surface only" query
 * physically cannot reach it. Carries only `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beat_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('beat_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('surface');
            $table->string('pov_anchor', 150);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beat_records');
    }
};
