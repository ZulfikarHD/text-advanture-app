<?php

use App\Enums\BlockAgent;
use App\Enums\BlockSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global library: `prompt_blocks` (DATABASE.md §3.14, ADR 0020).
 *
 * The single source of truth for every prompt block the assembler renders:
 * agent, section, label, purpose, source producers, fold instruction, order,
 * and leak rules. App-wide (no `story_id`). Seeded with ~15 rows in Sprint 6
 * (S-6.1.3); the `leak_rules` JSON names existing guards only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->enum('agent', array_column(BlockAgent::cases(), 'value'));
            $table->enum('section', array_column(BlockSection::cases(), 'value'));
            $table->string('label', 60);
            $table->text('purpose');
            $table->json('source_producers');
            $table->text('compile_instruction')->nullable();
            $table->json('leak_rules');
            $table->integer('order_index');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_blocks');
    }
};
