<?php

use App\Enums\EmotionSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `active_emotions` (DATABASE.md §4.6, ADR 0014).
 *
 * A child of `internal_states`: one free-text feeling with its own clock -
 * intensity, resting baseline, reversion rate, and off-screen drift cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_emotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_state_id')->constrained()->cascadeOnDelete();
            $table->string('emotion', 60);
            $table->unsignedTinyInteger('intensity');
            $table->unsignedTinyInteger('baseline');
            $table->decimal('reversion_rate', 4, 2)->nullable();
            $table->unsignedTinyInteger('drift_cap')->default(3);
            $table->enum('source', array_column(EmotionSource::cases(), 'value'));
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_clocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_emotions');
    }
};
