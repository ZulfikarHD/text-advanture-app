<?php

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `acquired_sensitivities` (DATABASE.md §4.7, ADR 0005).
 *
 * Runtime scar triggers a character picked up during a playthrough (vs the
 * authored `sensitivities`). Optionally links to the `axis_deltas` rupture that
 * installed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquired_sensitivities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->text('detect');
            $table->enum('target', array_column(SensitivityTarget::cases(), 'value'));
            $table->json('axes');
            $table->enum('weight', array_column(SensitivityWeight::cases(), 'value'));
            $table->enum('channel', array_column(SensitivityChannel::cases(), 'value'));
            $table->foreignId('installed_by_delta_id')->nullable()->constrained('axis_deltas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquired_sensitivities');
    }
};
