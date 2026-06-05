<?php

namespace App\Models;

use App\Enums\BlockAgent;
use App\Enums\BlockSection;
use Database\Factories\PromptBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PromptBlock - one entry in the prompt-block registry (ADR 0020).
 *
 * Global library row (no story scope) and the single source of truth for a
 * prompt block: which agent/section it renders into, its label, purpose,
 * source producers, fold instruction, order, and leak rules. Drives the
 * assembler and the human block reference. Seeded with ~15 rows in Sprint 6
 * (S-6.1.3); `leak_rules` names existing guards only.
 *
 * @property int $id
 * @property string $key
 * @property BlockAgent $agent
 * @property BlockSection $section
 * @property string $label
 * @property string $purpose
 * @property array<int, array<string, mixed>> $source_producers
 * @property string|null $compile_instruction
 * @property array<int, string> $leak_rules
 * @property int $order_index
 * @property bool $is_active
 */
#[Fillable([
    'key',
    'agent',
    'section',
    'label',
    'purpose',
    'source_producers',
    'compile_instruction',
    'leak_rules',
    'order_index',
    'is_active',
])]
class PromptBlock extends Model
{
    /** @use HasFactory<PromptBlockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent' => BlockAgent::class,
            'section' => BlockSection::class,
            'source_producers' => 'array',
            'leak_rules' => 'array',
            'order_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
