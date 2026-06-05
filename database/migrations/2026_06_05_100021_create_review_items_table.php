<?php

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `review_items` (DATABASE.md §4.12, ADR 0003/0012 §5).
 *
 * The one shared review queue (propose -> review -> commit), polymorphic by
 * `producer_type`. Authoring-time compiles (card/outline/bible) enqueue with a
 * null `session_id` - a deliberate authoring-realm row in a save-realm table.
 * Mutable (status/reviewed_at change), so it keeps full timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('play_sessions')->cascadeOnDelete();
            $table->enum('producer_type', array_column(ProducerType::cases(), 'value'));
            $table->unsignedBigInteger('producer_id')->nullable();
            $table->json('payload');
            $table->enum('status', array_column(ReviewStatus::cases(), 'value'))->default(ReviewStatus::Pending->value);
            $table->json('edited_payload')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
