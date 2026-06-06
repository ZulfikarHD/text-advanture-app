<?php

namespace Tests\Unit\Services;

use App\Services\InteriorityHeuristic;
use Tests\TestCase;

/**
 * Unit tests for the world-fact discipline heuristic (S-3.1.2, ADR 0013 §5).
 *
 * Covers each interiority category it should flag (feeling, intent, concealment,
 * private state) and the false-positive guard: a world fact that merely contains
 * an emotive word must NOT be flagged.
 */
class InteriorityHeuristicTest extends TestCase
{
    private InteriorityHeuristic $heuristic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->heuristic = new InteriorityHeuristic;
    }

    public function test_it_flags_a_character_privately_feeling_an_emotion(): void
    {
        $this->assertTrue($this->heuristic->hasInteriority('She secretly loves the archivist.'));
    }

    public function test_it_flags_hidden_intent(): void
    {
        $this->assertTrue($this->heuristic->hasInteriority('He wants to seize the throne before dawn.'));
    }

    public function test_it_flags_concealed_knowledge(): void
    {
        $this->assertTrue($this->heuristic->hasInteriority('Luna knows but will not admit the diagnosis.'));
    }

    public function test_it_flags_a_possessive_private_state(): void
    {
        $this->assertTrue($this->heuristic->hasInteriority('Her hidden agenda drives the whole conspiracy.'));
    }

    public function test_it_returns_the_offending_phrase(): void
    {
        $signals = $this->heuristic->flag('She still fears the dark.');

        $this->assertSame('She still fears', $signals[0]['phrase']);
    }

    public function test_a_clean_world_fact_is_not_flagged(): void
    {
        $content = 'The Crystal Hollow is a sealed Aether sink beneath the old city, suffused with Link Resonance.';

        $this->assertFalse($this->heuristic->hasInteriority($content));
    }

    public function test_a_world_fact_with_an_emotive_word_is_not_a_false_positive(): void
    {
        // "gloves feel" has no personal subject, so it reads as a world fact.
        $content = 'The suppressor gloves feel cold and dampen Aether resonance.';

        $this->assertFalse($this->heuristic->hasInteriority($content));
    }

    public function test_empty_content_is_not_flagged(): void
    {
        $this->assertSame([], $this->heuristic->flag(''));
    }
}
