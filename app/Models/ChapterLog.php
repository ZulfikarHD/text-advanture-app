<?php

namespace App\Models;

use Database\Factories\ChapterLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ChapterLog - per-chapter continuity rollup for a save (ADR 0016).
 *
 * An optional summary plus the key beat `events` for continuity. Carries only
 * `created_at` (no `updated_at`); the summary may be filled in later.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $chapter_id
 * @property string|null $summary
 * @property array<int, mixed> $events
 * @property Carbon $created_at
 */
#[Fillable(['session_id', 'chapter_id', 'summary', 'events'])]
class ChapterLog extends Model
{
    /** @use HasFactory<ChapterLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
