<?php

namespace Database\Seeders;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityWeight;
use App\Models\UniversalPrior;
use Illuminate\Database\Seeder;

/**
 * Seed the universal-priors library (S-6.1.1, ADR 0005).
 *
 * The shared baseline human reactions appraisal starts from before a
 * character's own sensitivities apply. ADR 0005 pins four priors and their axis
 * directions verbatim; it calls them "weak baseline" reactions, so each is
 * seeded as a low-weight, drift-only nudge (the exact `detect` text, weight, and
 * channel are editable defaults - see PLACEHOLDER_TRACKING PH-22).
 *
 * Idempotent: keyed on `slug`, safe to re-run.
 */
class UniversalPriorSeeder extends Seeder
{
    /**
     * The ADR 0005 baseline reactions.
     *
     * @var list<array{slug: string, detect: string, axes: array<string, string>}>
     */
    private const PRIORS = [
        [
            'slug' => 'insult',
            'detect' => 'someone insults, mocks, or demeans the character',
            'axes' => ['affection' => 'down'],
        ],
        [
            'slug' => 'kindness',
            'detect' => 'someone is kind, warm, or generous toward the character',
            'axes' => ['affection' => 'up'],
        ],
        [
            'slug' => 'threat',
            'detect' => 'someone threatens, menaces, or endangers the character',
            'axes' => ['fear' => 'up'],
        ],
        [
            'slug' => 'broken_promise',
            'detect' => 'someone breaks a promise or commitment made to the character',
            'axes' => ['trust' => 'down'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PRIORS as $prior) {
            UniversalPrior::updateOrCreate(
                ['slug' => $prior['slug']],
                [
                    'detect' => $prior['detect'],
                    'axes' => $prior['axes'],
                    // Weak baseline reactions: low salience, drift-only (ADR 0005).
                    'default_weight' => SensitivityWeight::Low,
                    'channel' => SensitivityChannel::DriftOnly,
                ],
            );
        }
    }
}
