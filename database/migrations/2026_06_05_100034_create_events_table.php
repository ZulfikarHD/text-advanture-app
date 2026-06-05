<?php

use App\Enums\EventType;
use App\Enums\Handoff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `events` (DATABASE.md §4.15, ADR 0016).
 *
 * The immediate-context timeline for a save: each narration / player input /
 * NPC action / system entry with its optional handoff signal and token
 * estimate. Compacted into `scene_summaries` at SCENE_DONE to bound growth.
 * Carries only `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('beat_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', array_column(EventType::cases(), 'value'));
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->json('delivery')->nullable();
            $table->enum('handoff', array_column(Handoff::cases(), 'value'))->nullable();
            $table->integer('token_estimate')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
