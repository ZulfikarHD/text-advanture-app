<?php

use App\Enums\ModelTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `characters` (DATABASE.md §3.2, ADR 0001/0002/0007).
 *
 * A story's cast. `bible_path` references repo markdown that is never injected
 * (ADR 0001). `(story_id, slug)` is unique per story.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('name', 150);
            $table->string('bible_path', 255)->nullable();
            $table->unsignedTinyInteger('base_opacity');
            $table->json('live_axes');
            $table->enum('model_tier', array_column(ModelTier::cases(), 'value'));
            $table->boolean('is_player')->default(false);
            $table->timestamps();

            $table->unique(['story_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
