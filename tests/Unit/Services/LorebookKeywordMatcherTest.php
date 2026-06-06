<?php

namespace Tests\Unit\Services;

use App\Models\Chapter;
use App\Models\LorebookEntry;
use App\Services\LorebookKeywordMatcher;
use Tests\TestCase;

/**
 * Unit tests for the canonical lorebook keyword matcher (S-3.2.1, ADR 0013 §5).
 *
 * Pure matching logic, exercised on in-memory models (no database): substring
 * matching, multi-word phrases, the reveal-chapter clamp, and the triggered vs
 * withheld split that the preview surface renders.
 */
class LorebookKeywordMatcherTest extends TestCase
{
    private LorebookKeywordMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new LorebookKeywordMatcher;
    }

    public function test_keyword_match_is_case_insensitive_substring(): void
    {
        $entry = $this->entry(['gloves']);

        $matched = $this->matcher->matchedKeywords($entry, 'She adjusted her suppressor GLOVES.');

        $this->assertSame(['gloves'], $matched);
    }

    public function test_multi_word_keyword_matches_as_a_phrase(): void
    {
        $entry = $this->entry(['Crystal Hollow']);

        $matched = $this->matcher->matchedKeywords($entry, 'Deep within the crystal hollow, the air hummed.');

        $this->assertSame(['Crystal Hollow'], $matched);
    }

    public function test_no_matching_keyword_returns_empty(): void
    {
        $entry = $this->entry(['dragon']);

        $this->assertSame([], $this->matcher->matchedKeywords($entry, 'She touched the Aether.'));
    }

    public function test_preview_excludes_entries_with_no_keyword_match(): void
    {
        $entries = [$this->entry(['Aether'], id: 1), $this->entry(['dragon'], id: 2)];

        $result = $this->matcher->preview($entries, 'A surge of Aether filled the room.', null);

        $this->assertCount(1, $result['triggered']);
        $this->assertSame(1, $result['triggered'][0]['id']);
    }

    public function test_entry_is_withheld_when_reveal_is_later_than_previewed_chapter(): void
    {
        $entry = $this->entry(['gloves'], revealNumber: 3);

        $result = $this->matcher->preview([$entry], 'the suppressor gloves', previewChapterNumber: 1);

        $this->assertCount(0, $result['triggered']);
        $this->assertCount(1, $result['withheld']);
    }

    public function test_entry_is_triggered_when_previewing_at_the_reveal_chapter(): void
    {
        $entry = $this->entry(['gloves'], revealNumber: 3);

        $result = $this->matcher->preview([$entry], 'the suppressor gloves', previewChapterNumber: 3);

        $this->assertCount(1, $result['triggered']);
        $this->assertCount(0, $result['withheld']);
    }

    public function test_no_previewed_chapter_applies_no_reveal_clamp(): void
    {
        $entry = $this->entry(['gloves'], revealNumber: 3);

        $result = $this->matcher->preview([$entry], 'the suppressor gloves', previewChapterNumber: null);

        $this->assertCount(1, $result['triggered']);
    }

    public function test_withheld_entry_carries_its_reveal_chapter(): void
    {
        $entry = $this->entry(['gloves'], revealNumber: 7);

        $result = $this->matcher->preview([$entry], 'the suppressor gloves', previewChapterNumber: 2);

        $this->assertSame(7, $result['withheld'][0]['minRevealChapter']['number']);
    }

    /**
     * Build an in-memory lorebook entry, optionally with a reveal chapter.
     *
     * @param  list<string>  $keywords  The entry's keywords.
     * @param  int|null  $revealNumber  The minimum reveal chapter number, if gated.
     * @param  int  $id  The entry id surfaced in the preview result.
     */
    private function entry(array $keywords, ?int $revealNumber = null, int $id = 1): LorebookEntry
    {
        $entry = new LorebookEntry([
            'title' => null,
            'keywords' => $keywords,
            'content' => 'World fact.',
        ]);
        $entry->id = $id;

        if ($revealNumber === null) {
            $entry->setRelation('minRevealChapter', null);

            return $entry;
        }

        $chapter = new Chapter(['number' => $revealNumber, 'title' => "Chapter {$revealNumber}"]);
        $chapter->id = 100 + $revealNumber;
        $entry->setRelation('minRevealChapter', $chapter);

        return $entry;
    }
}
