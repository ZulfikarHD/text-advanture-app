<?php

namespace App\Services;

use App\Enums\ModelTier;
use App\Http\Requests\Stories\StoreCharacterRequest;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Minimal manual character authoring (S-1.1.1 / S-1.1.2, ADR 0018 §2 manual mode).
 *
 * Creates / updates / deletes a story's characters as owner-scoped atomic
 * operations, with no LLM call. A character's minimal fields (`appearance`,
 * `folded_identity`, `knowledge_boundary`) live on the per-`(character, chapter)`
 * {@see CharacterCard}, whose `chapter_id` is mandatory — so the service ensures
 * a default `Chapter 1` (the novel's backbone) before committing the chapter-1
 * card. The full AI/hybrid creation + bible→card compile pipeline lands in a
 * later phase; this is the manual slice only (no edges / registers /
 * sensitivities; `live_axes` is empty this phase).
 *
 * Exactly-one-player and the NPC-only mandatory `knowledge_boundary` are enforced
 * at the request layer ({@see StoreCharacterRequest});
 * this service is the persistence boundary.
 */
class CharacterService
{
    /**
     * Create a character (and its chapter-1 card) under the given story.
     *
     * @param  Story  $story  The owner-scoped parent story.
     * @param  array{name: string, is_player?: bool, appearance: string, base_opacity: int|string, folded_identity?: string|null, knowledge_boundary?: array{knows?: list<string>, does_not_know?: list<string>}|null}  $data
     */
    public function create(Story $story, array $data): Character
    {
        return DB::transaction(function () use ($story, $data): Character {
            $isPlayer = (bool) ($data['is_player'] ?? false);
            $chapter = $this->ensureFirstChapter($story);

            $character = $story->characters()->create([
                'slug' => $this->deriveUniqueSlug($story->getKey(), $data['name']),
                'name' => $data['name'],
                'bible_path' => null,
                'base_opacity' => (int) $data['base_opacity'],
                // Edges / live axes are authored in a later phase (none this phase).
                'live_axes' => [],
                'model_tier' => $isPlayer ? ModelTier::Minor : ModelTier::Major,
                'is_player' => $isPlayer,
            ]);

            $character->cards()->create($this->cardAttributes($chapter, $isPlayer, $data));

            return $character;
        });
    }

    /**
     * Update a character and its chapter-1 card.
     *
     * The player flag may change between modes; `model_tier` follows it and the
     * NPC-only card fields are reset to their empty player shape when switching
     * to the player so no stale interiority lingers.
     *
     * @param  Character  $character  The character to update (already scope-authorized).
     * @param  array{name: string, is_player?: bool, appearance: string, base_opacity: int|string, folded_identity?: string|null, knowledge_boundary?: array{knows?: list<string>, does_not_know?: list<string>}|null}  $data
     */
    public function update(Character $character, array $data): Character
    {
        return DB::transaction(function () use ($character, $data): Character {
            $isPlayer = (bool) ($data['is_player'] ?? false);
            $chapter = $this->ensureFirstChapter($character->story);

            $character->update([
                'name' => $data['name'],
                'base_opacity' => (int) $data['base_opacity'],
                'model_tier' => $isPlayer ? ModelTier::Minor : ModelTier::Major,
                'is_player' => $isPlayer,
            ]);

            $card = $character->cards()->firstOrNew(['chapter_id' => $chapter->getKey()]);
            $card->fill($this->cardAttributes($chapter, $isPlayer, $data))->save();

            return $character->refresh();
        });
    }

    /**
     * Delete a character and cascade to its cards.
     *
     * @param  Character  $character  The character to remove (already scope-authorized).
     */
    public function delete(Character $character): void
    {
        DB::transaction(fn () => $character->delete());
    }

    /**
     * Build the chapter-1 card attributes for the given mode.
     *
     * A player carries appearance only — no simulated interiority — so its
     * `folded_identity` and `knowledge_boundary` are stored empty (the human
     * supplies the behavior). An NPC carries the authored, normalised boundary.
     *
     * @param  array{folded_identity?: string|null, knowledge_boundary?: array{knows?: list<string>, does_not_know?: list<string>}|null, appearance: string}  $data
     * @return array<string, mixed>
     */
    private function cardAttributes(Chapter $chapter, bool $isPlayer, array $data): array
    {
        return [
            'chapter_id' => $chapter->getKey(),
            'folded_identity' => $isPlayer ? '' : ($data['folded_identity'] ?? ''),
            'knowledge_boundary' => $isPlayer
                ? ['knows' => [], 'does_not_know' => []]
                : $this->normalizeKnowledgeBoundary($data['knowledge_boundary'] ?? []),
            'disposition_priors' => [],
            'voice' => [],
            'tells' => [],
            'appearance' => $data['appearance'],
            'compiled_source_hash' => null,
            'review_item_id' => null,
        ];
    }

    /**
     * Ensure the story has its backbone `Chapter 1` and return it.
     *
     * Characters are tied to chapters (Novel-Crafter model); the chapter-1 card
     * needs a chapter to anchor to. This keeps E1.1 functional before Structure
     * (E1.2) is authored — that surface later refines this same chapter rather
     * than re-creating it.
     *
     * @param  Story  $story  The story whose first chapter anchors the card.
     */
    private function ensureFirstChapter(Story $story): Chapter
    {
        return $story->chapters()->firstOrCreate(
            ['number' => 1],
            [
                'title' => 'Chapter 1',
                'pov_default' => app(StorySettingsService::class)->resolveDefaultPov($story)->value,
            ],
        );
    }

    /**
     * Trim, drop empties, and de-duplicate the two knowledge-boundary lists.
     *
     * The runtime NPC `IDENTITY`/`SCENE_EXCERPT` blocks (Phase 2) and the `NUDGE`
     * leak-check (Phase 4) read this shape, so a clean `{ knows, does_not_know }`
     * keeps those consumers predictable.
     *
     * @param  array{knows?: list<string>, does_not_know?: list<string>}  $boundary
     * @return array{knows: list<string>, does_not_know: list<string>}
     */
    private function normalizeKnowledgeBoundary(array $boundary): array
    {
        return [
            'knows' => $this->normalizeList($boundary['knows'] ?? []),
            'does_not_know' => $this->normalizeList($boundary['does_not_know'] ?? []),
        ];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function normalizeList(array $items): array
    {
        return collect($items)
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Derive a URL-safe slug from a name, auto-suffixing until unique per story.
     *
     * Mirrors {@see StoryService::deriveUniqueSlug()} but scopes uniqueness to
     * the `(story_id, slug)` index rather than per owner.
     *
     * @param  int  $storyId  The parent story's id.
     * @param  string  $name  The character name to slugify.
     * @param  int|null  $exceptId  Character id to exclude (for updates).
     */
    public function deriveUniqueSlug(int $storyId, string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'character';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExistsForStory($storyId, $candidate, $exceptId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Check whether the slug already exists among the story's characters.
     */
    private function slugExistsForStory(int $storyId, string $slug, ?int $exceptId = null): bool
    {
        return Character::query()
            ->where('story_id', $storyId)
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
