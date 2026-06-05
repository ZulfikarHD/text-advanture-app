<?php

use App\Enums\NudgeLevel;
use App\Enums\StateNode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save realm: `play_sessions` (DATABASE.md §4.1, ADR 0012/0016).
 *
 * A save: a fork of the authoring template into evolving runtime state, plus
 * the narrator-loop position. Root of the save realm - every save-realm child
 * is FK-scoped to it via `session_id`.
 *
 * Named `play_sessions` (not `sessions`) because the framework already owns the
 * `sessions` table for the database session driver; child FK columns keep the
 * spec name `session_id` and constrain to this table (see PH-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->enum('state_node', array_column(StateNode::cases(), 'value'));
            $table->foreignId('current_chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->foreignId('current_scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->foreignId('current_beat_id')->nullable()->constrained('beats')->nullOnDelete();
            $table->integer('beat_word_count')->default(0);
            $table->integer('chapter_word_count')->default(0);
            $table->enum('nudge_level', array_column(NudgeLevel::cases(), 'value'))->nullable();
            $table->json('resume_anchor')->nullable();
            $table->json('narrative_clock')->nullable();
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_sessions');
    }
};
