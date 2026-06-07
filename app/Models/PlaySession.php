<?php

namespace App\Models;

use App\Enums\NudgeLevel;
use App\Enums\StateNode;
use Database\Factories\PlaySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * PlaySession - a save: a fork of the authoring template into runtime state
 * (ADR 0012/0016).
 *
 * Root of the save realm; every save-realm child is FK-scoped to it via
 * `session_id`. Named `PlaySession`/`play_sessions` because the framework owns
 * the `sessions` table for the database session driver (see PH-17). Holds the
 * narrator-loop position (state node, current chapter/scene/beat, word clocks,
 * nudge level, resume anchor, narrative clock).
 *
 * @property int $id
 * @property int $story_id
 * @property string $name
 * @property StateNode $state_node
 * @property int|null $current_chapter_id
 * @property int|null $current_scene_id
 * @property int|null $current_beat_id
 * @property int $beat_word_count
 * @property int $chapter_word_count
 * @property NudgeLevel|null $nudge_level
 * @property array<string, mixed>|null $resume_anchor
 * @property array<string, mixed>|null $narrative_clock
 * @property Carbon|null $last_played_at
 */
#[Fillable([
    'story_id',
    'name',
    'state_node',
    'current_chapter_id',
    'current_scene_id',
    'current_beat_id',
    'beat_word_count',
    'chapter_word_count',
    'nudge_level',
    'resume_anchor',
    'narrative_clock',
    'last_played_at',
])]
class PlaySession extends Model
{
    /** @use HasFactory<PlaySessionFactory> */
    use HasFactory;

    protected $table = 'play_sessions';

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The authoring chapter this save is currently positioned at.
     *
     * Points at the immutable authoring realm (ADR 0012); a save references the
     * template's structure rather than copying it.
     *
     * @return BelongsTo<Chapter, $this>
     */
    public function currentChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'current_chapter_id');
    }

    /**
     * The authoring scene this save is currently positioned at.
     *
     * @return BelongsTo<Scene, $this>
     */
    public function currentScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'current_scene_id');
    }

    /**
     * The authoring beat this save is currently positioned at.
     *
     * @return BelongsTo<Beat, $this>
     */
    public function currentBeat(): BelongsTo
    {
        return $this->belongsTo(Beat::class, 'current_beat_id');
    }

    /**
     * @return HasMany<RelationshipEdge, $this>
     */
    public function relationshipEdges(): HasMany
    {
        return $this->hasMany(RelationshipEdge::class, 'session_id');
    }

    /**
     * @return HasMany<InternalState, $this>
     */
    public function internalStates(): HasMany
    {
        return $this->hasMany(InternalState::class, 'session_id');
    }

    /**
     * @return HasMany<BeatRecord, $this>
     */
    public function beatRecords(): HasMany
    {
        return $this->hasMany(BeatRecord::class, 'session_id');
    }

    /**
     * @return HasMany<ReviewItem, $this>
     */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class, 'session_id');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'session_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_node' => StateNode::class,
            'nudge_level' => NudgeLevel::class,
            'beat_word_count' => 'integer',
            'chapter_word_count' => 'integer',
            'resume_anchor' => 'array',
            'narrative_clock' => 'array',
            'last_played_at' => 'datetime',
        ];
    }
}
