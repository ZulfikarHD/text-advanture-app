<?php

namespace App\Enums;

/**
 * The kind of producer that enqueued a review item (ADR 0003/0013/0018/0019).
 *
 * Makes the single review queue polymorphic: every engine producer (deltas,
 * emotion deltas, nudge/card/outline/bible compiles, beat records) tags its
 * proposal so the gate can render and commit it. Mirrors the
 * `review_items.producer_type` DB enum.
 */
enum ProducerType: string
{
    case Delta = 'delta';
    case EmotionDelta = 'emotion_delta';
    case NudgeCompile = 'nudge_compile';
    case BeatRecord = 'beat_record';
    case CardCompile = 'card_compile';
    case BibleGenerate = 'bible_generate';
    case OutlineCompile = 'outline_compile';
}
