<?php

namespace App\Services;

use App\Enums\LlmRole;
use App\Exceptions\Llm\UnresolvedModelRoleException;
use App\Models\Beat;
use App\Models\Scene;
use App\Models\Story;
use App\Services\Llm\ModelRoleResolver;

/**
 * Story overview: derived authoring inventory + play-readiness (S-1.2.2).
 *
 * Every figure here is computed on read and never stored: counts aggregate the
 * story's authoring rows (plus its save count), and play-readiness is a derived
 * gate recomputed each time the overview is opened. The readiness checks are
 * built to be reused by the full play-readiness checklist UI (E2.1 / S-2.1.2).
 */
class StoryOverviewService
{
    public function __construct(private readonly ModelRoleResolver $modelRoles) {}

    /**
     * Aggregate the story's authoring inventory plus its save count.
     *
     * @param  Story  $story  The story to inventory.
     * @return array{characters: int, chapters: int, scenes: int, beats: int, lorebookEntries: int, revealLedgerEntries: int, saves: int}
     */
    public function counts(Story $story): array
    {
        $chapterIds = $story->chapters()->select('id');
        $sceneIds = Scene::query()->whereIn('chapter_id', $chapterIds)->select('id');

        return [
            'characters' => $story->characters()->count(),
            'chapters' => $story->chapters()->count(),
            'scenes' => Scene::query()->whereIn('chapter_id', $chapterIds)->count(),
            'beats' => Beat::query()->whereIn('scene_id', $sceneIds)->count(),
            'lorebookEntries' => $story->lorebookEntries()->count(),
            'revealLedgerEntries' => $story->revealLedgerEntries()->count(),
            'saves' => $story->playSessions()->count(),
        ];
    }

    /**
     * Evaluate whether the story is play-ready, enumerating every requirement.
     *
     * Requirements: at least one character, at least one chapter that contains a
     * scene and a beat (guaranteed by any beat existing, since beats nest under
     * scene→chapter), and a resolvable model for every engine role (per-story
     * override → global default).
     *
     * @param  Story  $story  The story to evaluate.
     * @return array{ready: bool, requirements: list<array{key: string, label: string, met: bool, detail: string}>}
     */
    public function readiness(Story $story): array
    {
        $counts = $this->counts($story);
        $unresolvedRoles = $this->unresolvedRoles($story);

        $requirements = [
            [
                'key' => 'characters',
                'label' => 'At least one character',
                'met' => $counts['characters'] >= 1,
                'detail' => $counts['characters'] >= 1
                    ? "{$counts['characters']} character(s) authored."
                    : 'Add a character so the cast has someone to play.',
            ],
            [
                'key' => 'structure',
                'label' => 'A chapter with a scene and a beat',
                'met' => $counts['beats'] >= 1,
                'detail' => $counts['beats'] >= 1
                    ? 'Story structure has at least one playable beat.'
                    : 'Add a chapter, scene, and beat to give the engine something to direct.',
            ],
            [
                'key' => 'model_config',
                'label' => 'A resolvable model for every engine role',
                'met' => $unresolvedRoles === [],
                'detail' => $unresolvedRoles === []
                    ? 'Every engine role resolves to a model.'
                    : 'No resolvable model for: '.implode(', ', $unresolvedRoles).'.',
            ],
        ];

        $ready = collect($requirements)->every(fn (array $req): bool => $req['met']);

        return [
            'ready' => $ready,
            'requirements' => $requirements,
        ];
    }

    /**
     * Collect the labels of engine roles that resolve to no active model.
     *
     * @param  Story  $story  The story whose per-story overrides are considered first.
     * @return list<string> Human labels of unresolved roles (empty when all resolve).
     */
    private function unresolvedRoles(Story $story): array
    {
        $unresolved = [];

        foreach (LlmRole::cases() as $role) {
            try {
                $this->modelRoles->resolve($role, $story);
            } catch (UnresolvedModelRoleException) {
                $unresolved[] = $role->label();
            }
        }

        return $unresolved;
    }
}
