<?php

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-scope the review queue: add `user_id` to `review_items` (S-6.2, ADR 0012 §5).
 *
 * Authoring-time proposals (card/outline/bible compiles) enqueue with a null
 * `session_id`, so the session -> story -> owner chain cannot carry ownership for
 * every row. A direct nullable `user_id` lets the {@see BelongsToOwner} scope
 * fail-closed every read to the current owner (mirrors the `llm_calls` PH-20
 * decision). Nullable keeps unauthenticated console/seed inserts valid; real
 * proposals stamp it from the current owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
