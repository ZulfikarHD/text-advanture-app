<?php

use App\Enums\ElapsedBucket;
use App\Enums\ElapsedSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoring realm: `scenes` (DATABASE.md §3.11, ADR 0009/0015).
 *
 * Ordered scenes within a chapter, each carrying its POV contract and declared
 * in-world elapsed-time bucket. `(chapter_id, number)` is unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->integer('number');
            $table->string('pov_mode', 60);
            $table->string('pov_anchor', 150);
            $table->string('tone', 120)->nullable();
            $table->text('setting')->nullable();
            $table->json('present_characters')->nullable();
            $table->enum('elapsed_bucket', array_column(ElapsedBucket::cases(), 'value'))
                ->default(ElapsedBucket::Continuous->value);
            $table->enum('elapsed_source', array_column(ElapsedSource::cases(), 'value'));
            $table->timestamps();

            $table->unique(['chapter_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
