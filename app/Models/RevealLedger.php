<?php

namespace App\Models;

use Database\Factories\RevealLedgerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RevealLedger - a load-bearing secret mapped to its reveal chapter
 * (ADR 0013 §3).
 *
 * Drives the card compile clamp. A null `character_id` denotes a world secret
 * rather than a per-character one. Authoring-realm child of {@see Story}.
 *
 * @property int $id
 * @property int $story_id
 * @property int|null $character_id
 * @property string $fact
 * @property int $reveal_chapter_id
 * @property array<int, string> $who_knows
 * @property string|null $notes
 */
#[Fillable([
    'story_id',
    'character_id',
    'fact',
    'reveal_chapter_id',
    'who_knows',
    'notes',
])]
class RevealLedger extends Model
{
    /** @use HasFactory<RevealLedgerFactory> */
    use HasFactory;

    /**
     * Singular table name per the schema spec (DATABASE.md §3.4).
     *
     * @var string
     */
    protected $table = 'reveal_ledger';

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The character the secret is about (null = world secret).
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * The chapter at which the fact becomes known.
     *
     * @return BelongsTo<Chapter, $this>
     */
    public function revealChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'reveal_chapter_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'who_knows' => 'array',
        ];
    }
}
