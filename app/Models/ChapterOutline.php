<?php

namespace App\Models;

use App\Enums\OutlineStatus;
use Database\Factories\ChapterOutlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChapterOutline - the author's raw outline text and its compile linkage
 * (ADR 0019).
 *
 * `raw_text` is never injected at runtime. `chapter_id` is set once the outline
 * compiles into a chapter (an outline may span chapters). `review_item_id`
 * links to the save-realm `outline_compile` {@see ReviewItem} (FK enforced as
 * of Sprint 4, PH-16 resolved).
 *
 * @property int $id
 * @property int $story_id
 * @property int|null $chapter_id
 * @property string $raw_text
 * @property OutlineStatus $status
 * @property int|null $review_item_id
 */
#[Fillable([
    'story_id',
    'chapter_id',
    'raw_text',
    'status',
    'review_item_id',
])]
class ChapterOutline extends Model
{
    /** @use HasFactory<ChapterOutlineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The chapter this outline compiled into, if any.
     *
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * The `outline_compile` review record this outline was committed from.
     *
     * @return BelongsTo<ReviewItem, $this>
     */
    public function reviewItem(): BelongsTo
    {
        return $this->belongsTo(ReviewItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutlineStatus::class,
        ];
    }
}
