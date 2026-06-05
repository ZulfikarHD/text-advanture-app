<?php

use App\Enums\AwarenessMode;
use App\Enums\Axis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `edge_axes` (DATABASE.md §4.3, ADR 0002/0004).
 *
 * One row per live axis on a relationship edge; holds the materialized current
 * value plus the clamps, rates, high-water marks, baseline, and latched scar
 * the delta engine and decay read. Unique per `(relationship_edge_id, axis)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_axes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_edge_id')->constrained()->cascadeOnDelete();
            $table->enum('axis', array_column(Axis::cases(), 'value'));
            $table->smallInteger('value');
            $table->enum('awareness_mode', array_column(AwarenessMode::cases(), 'value'));
            $table->smallInteger('soft_floor');
            $table->smallInteger('soft_cap');
            $table->smallInteger('hard_floor');
            $table->smallInteger('hard_cap');
            $table->decimal('gain_rate', 4, 2);
            $table->decimal('loss_rate', 4, 2);
            $table->smallInteger('peak_up');
            $table->smallInteger('peak_down');
            $table->smallInteger('baseline');
            $table->smallInteger('latch_threshold')->nullable();
            $table->json('scar')->nullable();
            $table->timestamps();

            $table->unique(['relationship_edge_id', 'axis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_axes');
    }
};
