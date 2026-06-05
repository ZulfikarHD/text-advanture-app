<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves PH-16: declares the three FK constraints that Sprint 3 deferred.
 *
 * The columns (`registers.archetype_id`, `character_cards.review_item_id`,
 * `chapter_outlines.review_item_id`) were created nullable in Sprint 3 but
 * could not carry a constraint because their referenced tables
 * (`register_archetypes`, `review_items`) did not exist yet. Now that Sprint 4
 * has migrated both, the constraints are added here. All three are nullable, so
 * the link is `nullOnDelete`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->foreign('archetype_id')
                ->references('id')->on('register_archetypes')
                ->nullOnDelete();
        });

        Schema::table('character_cards', function (Blueprint $table) {
            $table->foreign('review_item_id')
                ->references('id')->on('review_items')
                ->nullOnDelete();
        });

        Schema::table('chapter_outlines', function (Blueprint $table) {
            $table->foreign('review_item_id')
                ->references('id')->on('review_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropForeign(['archetype_id']);
        });

        Schema::table('character_cards', function (Blueprint $table) {
            $table->dropForeign(['review_item_id']);
        });

        Schema::table('chapter_outlines', function (Blueprint $table) {
            $table->dropForeign(['review_item_id']);
        });
    }
};
