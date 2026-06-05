<?php

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `sensitivities` (DATABASE.md §3.7, ADR 0005).
 *
 * Authored appraisal amplifiers / special-cases per character. `(character_id,
 * slug)` is unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitivities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            $table->text('detect');
            $table->enum('target', array_column(SensitivityTarget::cases(), 'value'));
            $table->json('axes');
            $table->enum('weight', array_column(SensitivityWeight::cases(), 'value'));
            $table->enum('channel', array_column(SensitivityChannel::cases(), 'value'));
            $table->timestamps();

            $table->unique(['character_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitivities');
    }
};
