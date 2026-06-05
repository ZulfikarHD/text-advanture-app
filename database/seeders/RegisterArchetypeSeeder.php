<?php

namespace Database\Seeders;

use App\Models\RegisterArchetype;
use Illuminate\Database\Seeder;

/**
 * Seed the register-archetype library (S-6.1.1, ADR 0006).
 *
 * Reusable conversational-grammar skeletons defined over the fixed canonical
 * dimension set (disclosure, proximity, flow, deflection, sincerity, composure,
 * reads_target). `speech` and `tells` stay card-level (a character binds them
 * per ADR 0006), so they are not part of the shared skeleton.
 *
 * `one_way_mirror` and `romantic_deflection` are taken from the Luna worked
 * example in ADR 0006; `unguarded` and `wary` are named in the ADR but carry no
 * documented profile, so their dimensions are authored editable defaults
 * (PLACEHOLDER_TRACKING PH-23).
 *
 * Idempotent: keyed on `slug`, safe to re-run.
 */
class RegisterArchetypeSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, name: string, description: string, dimensions: array<string, string>}>
     */
    private const ARCHETYPES = [
        [
            'slug' => 'one_way_mirror',
            'name' => 'One-way mirror',
            'description' => 'Reads the other accurately while sealing the self; genuine warmth used with conscious precision.',
            'dimensions' => [
                'disclosure' => 'sealed',
                'proximity' => 'controlled',
                'flow' => 'clean-exit',
                'deflection' => 'invisible',
                'sincerity' => 'warm-non-answer',
                'composure' => 'unbreakable',
                'reads_target' => 'accurate',
            ],
        ],
        [
            'slug' => 'romantic_deflection',
            'name' => 'Romantic deflection',
            'description' => 'Holds a romantic approach at arm\'s length with a warm non-answer and a clean exit.',
            'dimensions' => [
                'disclosure' => 'sealed',
                'proximity' => 'distancing',
                'flow' => 'clean-exit',
                'deflection' => 'invisible',
                'sincerity' => 'warm-non-answer',
            ],
        ],
        [
            'slug' => 'unguarded',
            'name' => 'Unguarded',
            'description' => 'Open and direct, holding nothing back; the grammar of trust without defenses.',
            'dimensions' => [
                'disclosure' => 'open',
                'proximity' => 'warm',
                'flow' => 'extends-every-moment',
                'deflection' => 'none',
                'sincerity' => 'direct',
                'composure' => 'fragile',
                'reads_target' => 'accurate',
            ],
        ],
        [
            'slug' => 'wary',
            'name' => 'Wary',
            'description' => 'Guarded and distancing; engages but keeps an exit and reveals little.',
            'dimensions' => [
                'disclosure' => 'guarded',
                'proximity' => 'distancing',
                'flow' => 'clean-exit',
                'deflection' => 'none',
                'sincerity' => 'warm-non-answer',
                'composure' => 'unbreakable',
                'reads_target' => 'accurate',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ARCHETYPES as $archetype) {
            RegisterArchetype::updateOrCreate(
                ['slug' => $archetype['slug']],
                [
                    'name' => $archetype['name'],
                    'dimensions' => $archetype['dimensions'],
                    'description' => $archetype['description'],
                ],
            );
        }
    }
}
