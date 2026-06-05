<?php

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global library + per-story override: `model_profiles` (DATABASE.md §3.16,
 * ADR 0017 §2).
 *
 * The role -> model slug + params mapping. `Global` rows (null `story_id`) are
 * the defaults; `Story` rows override them. `(scope, story_id, role)` is unique
 * so resolution is unambiguous. Seeded in Sprint 6 (S-6.1.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_profiles', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', array_column(ModelScope::cases(), 'value'));
            $table->foreignId('story_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('role', array_column(LlmRole::cases(), 'value'));
            $table->string('model_slug', 120);
            $table->json('params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope', 'story_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_profiles');
    }
};
