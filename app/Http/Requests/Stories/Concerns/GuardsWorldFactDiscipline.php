<?php

namespace App\Http\Requests\Stories\Concerns;

use App\Services\InteriorityHeuristic;
use Illuminate\Validation\Validator;

/**
 * World-fact discipline gate for lorebook entry requests (S-3.1.2, ADR 0013 §5).
 *
 * Shared by the store and update lorebook requests so both enforce the same soft
 * gate: content that reads as a character's interiority is rejected unless the
 * author explicitly acknowledges saving it as a world fact. The heuristic is
 * advisory, so the gate steers rather than hard-blocks (a false positive is
 * overridable), while the default keeps interiority out of the lorebook and
 * preserves character isolation at injection time.
 */
trait GuardsWorldFactDiscipline
{
    /**
     * Reject content that reads as character interiority unless acknowledged.
     *
     * @param  Validator  $validator  The current request validator.
     * @param  InteriorityHeuristic  $heuristic  The world-fact discipline linter.
     */
    protected function guardWorldFactDiscipline(Validator $validator, InteriorityHeuristic $heuristic): void
    {
        // Soft gate: the author explicitly chose to keep this entry as a world fact.
        if ($this->boolean('acknowledge_interiority')) {
            return;
        }

        $content = $this->input('content');

        // Nothing to inspect; the required/string rules already cover emptiness.
        if (! is_string($content) || trim($content) === '') {
            return;
        }

        $signals = $heuristic->flag($content);

        if ($signals === []) {
            return;
        }

        $phrases = collect($signals)
            ->take(3)
            ->map(fn (array $signal): string => '"'.$signal['phrase'].'"')
            ->implode(', ');

        $validator->errors()->add('interiority', __(
            'This reads like a character\'s interiority (:phrases). The lorebook is for world facts only — move private feelings, secret intent, or hidden knowledge to the character cards.',
            ['phrases' => $phrases],
        ));
    }
}
