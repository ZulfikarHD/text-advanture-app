<?php

namespace App\Http\Requests\Stories\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for minimal manual beat requests (S-1.2.1).
 *
 * The `goal` is the only load-bearing beat field this phase — it is the beat's
 * satisfaction anchor the narrator steers toward. The full beat document
 * (`intent`, `word_budget`, `nudge_target`) is authored in Phase 4, so it is
 * neither accepted nor required here.
 */
trait GuardsBeatFields
{
    /**
     * The field rules common to creating and updating a beat.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function beatRules(): array
    {
        return [
            'goal' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function beatMessages(): array
    {
        return [
            'goal.required' => __('A goal is required — it is the beat\'s satisfaction anchor.'),
        ];
    }
}
