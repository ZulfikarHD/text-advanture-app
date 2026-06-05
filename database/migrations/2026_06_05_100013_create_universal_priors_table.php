<?php

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityWeight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global library: `universal_priors` (DATABASE.md §3.8, ADR 0005).
 *
 * App-wide baseline human reactions (insult, kindness, threat, broken promise)
 * that seed appraisal before any character-specific sensitivity. Carries no
 * `story_id`. Seeded in Sprint 6 (S-6.1.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universal_priors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->text('detect');
            $table->json('axes');
            $table->enum('default_weight', array_column(SensitivityWeight::cases(), 'value'));
            $table->enum('channel', array_column(SensitivityChannel::cases(), 'value'));
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universal_priors');
    }
};
