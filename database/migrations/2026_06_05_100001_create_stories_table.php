<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `stories` (DATABASE.md §3.1).
 *
 * Root of the authoring (template) realm and the first owner-scoped product
 * model: `user_id` carries ownership (ADR 0012); every child authoring row is
 * isolated transitively through its story. Immutable at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
