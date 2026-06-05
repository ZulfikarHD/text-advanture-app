<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only table backing Tests\Fixtures\OwnedFixture.
 *
 * Stands in for a real owned product model (stories, saves, ...) so the
 * account-isolation foundation (BelongsToOwner + OwnerScope + OwnerPolicy)
 * can be exercised before those tables exist. Never registered outside the
 * test suite (see Tests\TestCase::refreshApplication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owned_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owned_fixtures');
    }
};
