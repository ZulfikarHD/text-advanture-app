<?php

namespace Database\Seeders;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\ModelProfile;
use Illuminate\Database\Seeder;

/**
 * Seed the default global model profiles (S-6.1.4, ADR 0017 §2).
 *
 * One `Global` row (null `story_id`) per engine role so the resolver can map any
 * role to a model out of the box; per-story overrides come later. ADR 0017 §2
 * pins each role's tier (strong/mid/cheap); the strong and cheap example slugs
 * are taken from the ADR, the mid slug is an editable default since the ADR
 * names no mid example (PLACEHOLDER_TRACKING PH-26).
 *
 * Idempotent: keyed on `(scope, story_id, role)`, safe to re-run.
 */
class ModelProfileSeeder extends Seeder
{
    private const SLUG_STRONG = 'anthropic/claude-sonnet-4';

    private const SLUG_MID = 'anthropic/claude-3.5-sonnet';

    private const SLUG_CHEAP = 'anthropic/claude-3.5-haiku';

    /**
     * Role -> default model slug, following the ADR 0017 §2 tier table.
     *
     * @return array<string, string>
     */
    private function roleSlugs(): array
    {
        return [
            LlmRole::NarratorProse->value => self::SLUG_STRONG,
            LlmRole::Recorder->value => self::SLUG_STRONG,
            LlmRole::NpcMajor->value => self::SLUG_STRONG,
            LlmRole::NpcMinor->value => self::SLUG_CHEAP,
            LlmRole::Compiler->value => self::SLUG_STRONG,
            LlmRole::Appraiser->value => self::SLUG_MID,
            LlmRole::BeatJudge->value => self::SLUG_CHEAP,
            LlmRole::NudgeCompiler->value => self::SLUG_STRONG,
        ];
    }

    public function run(): void
    {
        $slugs = $this->roleSlugs();

        // Loop over every enum case so a new role can never ship without a default.
        foreach (LlmRole::cases() as $role) {
            ModelProfile::updateOrCreate(
                [
                    'scope' => ModelScope::Global,
                    'story_id' => null,
                    'role' => $role,
                ],
                [
                    'model_slug' => $slugs[$role->value],
                    'params' => ['temperature' => 0.7, 'max_tokens' => 2048],
                    'is_active' => true,
                ],
            );
        }
    }
}
