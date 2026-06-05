<?php

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-scope the call log: add `user_id` to `llm_calls` (S-5.3, ADR 0017 §5).
 *
 * The log may embed save-realm-sensitive content and must be owner-scoped, but
 * `session_id` / `story_id` are both nullable (authoring-time calls have
 * neither), so they cannot carry ownership. A direct nullable `user_id` lets
 * the {@see BelongsToOwner} scope fail-closed every log
 * read to the current owner. Nullable keeps unauthenticated console/seed
 * inserts valid; real calls always stamp it from the request owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_calls', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('llm_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
