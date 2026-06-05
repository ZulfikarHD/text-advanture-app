<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `beat_true_states` (DATABASE.md §4.9, ADR 0010) - APPEND-ONLY.
 *
 * Per-character PRIVATE feeling/intent for a beat. Deliberately a SEPARATE
 * table from `beat_records` (not a column): structural isolation means a query
 * that selects only the public `surface` cannot pull any character's private
 * `private_text`. Reaches its own character only via its `[SELF]` block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beat_true_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beat_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->text('private_text');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beat_true_states');
    }
};
