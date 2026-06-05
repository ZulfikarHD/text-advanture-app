<?php

use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `llm_calls` (DATABASE.md §4.16, ADR 0017) - APPEND-ONLY.
 *
 * The cost/latency call log: role, resolved model, token usage, provider cost
 * (USD micro-units), latency, and status per call. A null `session_id` marks an
 * authoring-time call. `messages` is debug-gated and save-realm-sensitive (it
 * may embed a character's `true_state`) - never an agent-readable source.
 * Carries only `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('play_sessions')->nullOnDelete();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('role', array_column(LlmRole::cases(), 'value'));
            $table->string('model_slug', 120);
            $table->enum('status', array_column(LlmCallStatus::cases(), 'value'));
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->bigInteger('cost_micros_usd')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->json('messages')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_calls');
    }
};
