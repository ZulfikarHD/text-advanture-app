<?php

namespace Database\Seeders;

use App\Models\CharacterArchetype;
use Illuminate\Database\Seeder;

/**
 * Seed the character-archetype library (S-6.1.2, ADR 0018).
 *
 * A character archetype bundles a whole seedable character shape (opacity, live
 * axes, disposition priors, registers, sensitivities, voice) so creation does
 * not start from a blank slate. `koakuma` is the one shape ADR 0018 defines; it
 * is a starting point, never a constraint - every field stays editable through
 * the review gate.
 *
 * ADR 0018 pins the axes, register/sensitivity slugs, and description verbatim;
 * `base_opacity` ("high"), the disposition-prior bodies, and the voice scaffold
 * are authored editable defaults (PLACEHOLDER_TRACKING PH-24).
 *
 * Idempotent: keyed on `slug`, safe to re-run.
 */
class CharacterArchetypeSeeder extends Seeder
{
    public function run(): void
    {
        CharacterArchetype::updateOrCreate(
            ['slug' => 'koakuma'],
            [
                'name' => 'Koakuma',
                'description' => 'genuine brightness used with conscious precision; one-way mirror',
                // "high" opacity from ADR 0018 mapped onto the 0-100 column (PH-24).
                'base_opacity' => 85,
                'suggested_live_axes' => ['affection', 'trust', 'romantic', 'fear'],
                // Seeds for the session-fork edge priors (ADR 0002), keyed by a
                // target trait. Authored scaffold, fully editable (PH-24).
                'default_disposition_priors' => [
                    'by_target_trait' => [
                        'shows_interest' => ['romantic' => 'low', 'affection' => 'low'],
                        'warm' => ['affection' => 'low'],
                        'hostile' => ['fear' => 'low', 'trust' => 'down'],
                    ],
                ],
                // References the register archetypes above plus one bespoke
                // register (no archetype), exactly as ADR 0018 lists them.
                'default_registers' => [
                    ['name' => 'koakuma_default', 'archetype' => 'one_way_mirror'],
                    ['name' => 'transparent_mess', 'archetype' => null],
                ],
                'default_sensitivities' => [
                    ['slug' => 'fear_of_abandonment'],
                    ['slug' => 'pitied_as_fragile', 'axes' => ['affection' => 'down']],
                ],
                'voice_scaffold' => [
                    'speech' => 'koakuma_voice',
                    'tells' => [],
                ],
            ],
        );
    }
}
