<?php

use App\Enums\NudgeLevel;
use App\Enums\NudgeSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `nudges` (DATABASE.md §4.11, ADR 0008/0015) - APPEND-ONLY.
 *
 * A directed-pressure instruction framed onto a character: kind, escalation
 * level, leak-checked text, and optional break-glass flag. Carries only
 * `created_at`; a re-issue is a new row, never a mutation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('play_sessions')->cascadeOnDelete();
            $table->foreignId('beat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->json('kind');
            $table->enum('level', array_column(NudgeLevel::cases(), 'value'));
            $table->text('text');
            $table->string('target', 120)->nullable();
            $table->string('goal', 255)->nullable();
            $table->enum('source', array_column(NudgeSource::cases(), 'value'));
            $table->boolean('is_break_glass')->default(false);
            $table->foreignId('review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nudges');
    }
};
