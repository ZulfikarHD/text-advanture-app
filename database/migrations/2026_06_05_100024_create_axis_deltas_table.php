<?php

use App\Enums\Axis;
use App\Enums\DeltaChannel;
use App\Enums\DeltaDirection;
use App\Enums\DeltaSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `axis_deltas` (DATABASE.md §4.4, ADR 0003) - APPEND-ONLY.
 *
 * The immutable audit log of every axis change; carries a mandatory `trigger`
 * (the matched reason names itself) and `value_before`/`value_after`. Carries
 * only `created_at` - never updated or deleted; corrections are new rows
 * through the review gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('axis_deltas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_edge_id')->constrained()->cascadeOnDelete();
            $table->enum('axis', array_column(Axis::cases(), 'value'));
            $table->enum('direction', array_column(DeltaDirection::cases(), 'value'));
            $table->decimal('magnitude', 5, 2);
            $table->enum('channel', array_column(DeltaChannel::cases(), 'value'));
            $table->string('trigger', 255);
            $table->decimal('confidence', 3, 2)->nullable();
            $table->smallInteger('value_before');
            $table->smallInteger('value_after');
            $table->enum('source', array_column(DeltaSource::cases(), 'value'));
            $table->foreignId('review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('axis_deltas');
    }
};
