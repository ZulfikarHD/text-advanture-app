<?php

namespace App\Services\Narrator;

use App\Enums\BlockAgent;
use App\Models\Character;
use App\Models\LorebookEntry;
use App\Models\PlaySession;
use App\Models\PromptBlock;
use App\Models\SceneSummary;
use App\Services\Narrator\Blocks\BlockProducer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Assembles the narrator's final prompt from the `prompt_blocks` registry
 * (S-4.1.1, ADR 0007/0020/0016).
 *
 * The narrator half of the shared registry-driven assembler. Selection and
 * order come entirely from the registry: active rows for `agent IN (narrator,
 * both)`, ordered by `order_index` within `section`. Each row is folded by the
 * {@see BlockProducer} registered for its key; a row with no producer (this
 * phase: MESH_AWARENESS, DIRECTOR_STATE) is skipped with no filler, and a
 * producer that yields nothing (e.g. RESUME_ANCHOR when not resuming) omits its
 * block. The result is an {@see AssembledPrompt} of messages only — the
 * structured prose call (S-4.2.1) resolves the model role and sends it.
 *
 * This phase folds deterministically (template, not the compiler LLM role); the
 * `compile_instruction` LLM-fold path is deferred (PH-25/PH-39).
 */
final class NarratorPromptAssembler
{
    /**
     * The trailing directive that closes the narrator's user message (ADR 0016 §2).
     */
    public const string CONTINUE_INSTRUCTION = 'Continue narrating.';

    /**
     * Block producers keyed by the registry key each one folds.
     *
     * @var array<string, BlockProducer>
     */
    private array $producers;

    /**
     * @param  iterable<BlockProducer>  $producers  The narrator block producers (container-tagged).
     */
    public function __construct(iterable $producers)
    {
        $map = [];

        foreach ($producers as $producer) {
            $map[$producer->blockKey()] = $producer;
        }

        $this->producers = $map;
    }

    /**
     * Assemble the narrator prompt for the given save's current position.
     *
     * @param  PlaySession  $session  The save the narrator turn runs on.
     * @return AssembledPrompt The lit blocks in registry order, renderable to chat messages.
     */
    public function assemble(PlaySession $session): AssembledPrompt
    {
        $context = $this->buildContext($session);
        $blocks = [];

        foreach ($this->activeNarratorBlocks() as $row) {
            $producer = $this->producers[$row->key] ?? null;

            // No producer means the block's data source is not built yet
            // (MESH_AWARENESS, DIRECTOR_STATE) — skip it rather than inject filler.
            if ($producer === null) {
                continue;
            }

            $body = $producer->produce($context);

            if ($body === null || trim($body) === '') {
                continue;
            }

            $blocks[] = new AssembledBlock($row->key, $row->label, $row->section, $body);
        }

        return new AssembledPrompt($blocks, self::CONTINUE_INSTRUCTION);
    }

    /**
     * The active narrator-facing registry rows, ordered by section then index.
     *
     * Selection and order are read from the rows, never code constants: section
     * orders `system` before `user`, and `order_index` orders within a section.
     *
     * @return Collection<int, PromptBlock>
     */
    private function activeNarratorBlocks(): Collection
    {
        return PromptBlock::query()
            ->whereIn('agent', [BlockAgent::Narrator->value, BlockAgent::Both->value])
            ->where('is_active', true)
            ->orderBy('section')
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Resolve every producer's data for this turn into a single context object.
     *
     * Reads the cast and lorebook by `story_id` directly (neither is owner-scoped)
     * so a narrator turn assembles regardless of auth context. The cast is keyed
     * by slug because scene references are slugs (PH-35), with the current
     * chapter's card eager-loaded for appearance.
     */
    private function buildContext(PlaySession $session): NarratorContext
    {
        $session->loadMissing(['currentScene', 'currentBeat', 'currentChapter']);

        $chapterId = $session->current_chapter_id;

        $cast = Character::query()
            ->where('story_id', $session->story_id)
            ->with(['cards' => fn (HasMany $query) => $query->where('chapter_id', $chapterId)])
            ->get()
            ->keyBy('slug');

        $lorebookEntries = LorebookEntry::query()
            ->where('story_id', $session->story_id)
            ->with('minRevealChapter')
            ->get();

        return new NarratorContext(
            session: $session,
            scene: $session->currentScene,
            beat: $session->currentBeat,
            chapter: $session->currentChapter,
            cast: $cast,
            lorebookEntries: $lorebookEntries,
            sceneSummaryText: $this->latestSceneSummary($session),
        );
    }

    /**
     * The most recent scene-summary rollup for the save's current scene, if any.
     *
     * Scene summaries are written at SCENE_DONE in E5.2; until then this is null
     * and the SCENE_STATE block carries only the present cast.
     */
    private function latestSceneSummary(PlaySession $session): ?string
    {
        if ($session->current_scene_id === null) {
            return null;
        }

        return SceneSummary::query()
            ->where('session_id', $session->id)
            ->where('scene_id', $session->current_scene_id)
            ->latest('created_at')
            ->value('summary');
    }
}
