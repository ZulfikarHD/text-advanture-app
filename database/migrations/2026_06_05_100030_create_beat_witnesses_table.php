<?php

use App\Enums\Fidelity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `beat_witnesses` (DATABASE.md §4.10, ADR 0007) - APPEND-ONLY.
 *
 * Who witnessed a beat record and at what `fidelity` (full / overheard /
 * partial) - drives how the beat excerpt is filtered and projected per NPC.
 * Carries only `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beat_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beat_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->enum('fidelity', array_column(Fidelity::cases(), 'value'));
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beat_witnesses');
    }
};
