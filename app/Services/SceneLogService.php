<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\PlaySession;
use App\Services\Narrator\NarratorTurnResult;

/**
 * Scene log — appends to a save's immediate-context timeline (S-5.2.1, ADR 0016).
 *
 * The narrator loop generates prose and the player writes back; this service is
 * where both become durable {@see Event} rows so the Writing/Play page can render
 * a readable scrollback (S-5.4.1) and later turns can read what came before. It
 * only appends — events are immutable once written, and `events.created_at`
 * preserves their order. Scene-summary compaction and the bounded token window
 * layer on top of this same log in later slices; this phase records the raw
 * narration / player-input entries with a rough token estimate.
 */
class SceneLogService
{
    /**
     * Append the narrator's prose for a turn, carrying its handoff signal.
     *
     * @param  PlaySession  $session  The save being narrated.
     * @param  NarratorTurnResult  $result  The validated prose + handoff for the turn.
     * @param  int|null  $beatId  The beat the prose was narrated against.
     * @return Event The persisted narration entry.
     */
    public function recordNarration(PlaySession $session, NarratorTurnResult $result, ?int $beatId): Event
    {
        return $session->events()->create([
            'beat_id' => $beatId,
            'type' => EventType::Narration,
            'content' => $result->prose,
            'handoff' => $result->handoff,
            'token_estimate' => $this->estimateTokens($result->prose),
        ]);
    }

    /**
     * Append the player's written contribution at a player moment (S-5.1.1).
     *
     * @param  PlaySession  $session  The save awaiting the player.
     * @param  string  $content  The player's written input.
     * @param  int|null  $beatId  The beat the input belongs to.
     * @return Event The persisted player-input entry.
     */
    public function recordPlayerInput(PlaySession $session, string $content, ?int $beatId): Event
    {
        return $session->events()->create([
            'beat_id' => $beatId,
            'type' => EventType::PlayerInput,
            'content' => $content,
            'token_estimate' => $this->estimateTokens($content),
        ]);
    }

    /**
     * Rough token estimate for the bounded immediate-context window (~4 chars/token).
     *
     * @param  string  $content  The text to estimate.
     */
    private function estimateTokens(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 4);
    }
}
