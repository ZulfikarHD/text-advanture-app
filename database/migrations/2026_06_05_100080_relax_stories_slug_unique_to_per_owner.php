<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relax the stories slug constraint from globally unique to per-owner unique.
 *
 * The AC for S-1.1.1 states "its slug is unique among the stories I own", so
 * two different owners are allowed to hold the same slug. The original global
 * unique was a Phase 1 placeholder before story CRUD existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique('stories_slug_unique');
            $table->unique(['user_id', 'slug'], 'stories_user_id_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique('stories_user_id_slug_unique');
            $table->unique('slug', 'stories_slug_unique');
        });
    }
};
