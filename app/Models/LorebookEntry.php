<?php

namespace App\Models;

use Database\Factories\LorebookEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LorebookEntry - a keyword-injected world fact (ADR 0013 §5).
 *
 * World facts only, never a character's interiority. `min_reveal_chapter_id`
 * optionally withholds the entry until a chapter. Authoring-realm child of
 * {@see Story}.
 *
 * @property int $id
 * @property int $story_id
 * @property string|null $title
 * @property array<int, string> $keywords
 * @property string $content
 * @property int|null $min_reveal_chapter_id
 */
#[Fillable([
    'story_id',
    'title',
    'keywords',
    'content',
    'min_reveal_chapter_id',
])]
class LorebookEntry extends Model
{
    /** @use HasFactory<LorebookEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The earliest chapter this entry may be injected (null = always).
     *
     * @return BelongsTo<Chapter, $this>
     */
    public function minRevealChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'min_reveal_chapter_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }
}
