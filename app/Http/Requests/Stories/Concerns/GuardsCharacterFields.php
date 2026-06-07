<?php

namespace App\Http\Requests\Stories\Concerns;

use App\Models\Character;
use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for minimal manual character requests (S-1.1.1 / S-1.1.2).
 *
 * Both the store and update requests share the same field rules and the two
 * mode-dependent guards: an NPC must carry a `folded_identity` and a non-empty
 * `knowledge_boundary` (mandatory even in this minimal manual mode, because
 * Phase 2/4 consumers depend on it), while a player carries appearance +
 * base_opacity only. The exactly-one-player rule is enforced here too, excluding
 * the bound character on update so editing the existing player is allowed.
 */
trait GuardsCharacterFields
{
    /**
     * The field rules common to creating and updating a character.
     *
     * Mode-dependent requirements (NPC `folded_identity` / `knowledge_boundary`)
     * are enforced in {@see guardCharacterFields()} so the message can name the
     * field rather than rely on a brittle conditional rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function characterRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'is_player' => ['boolean'],
            'appearance' => ['required', 'string', 'max:2000'],
            'base_opacity' => ['required', 'integer', 'min:0', 'max:100'],
            'folded_identity' => ['nullable', 'string', 'max:5000'],
            'knowledge_boundary' => ['nullable', 'array'],
            'knowledge_boundary.knows' => ['nullable', 'array'],
            'knowledge_boundary.knows.*' => ['string', 'max:255'],
            'knowledge_boundary.does_not_know' => ['nullable', 'array'],
            'knowledge_boundary.does_not_know.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function characterMessages(): array
    {
        return [
            'name.required' => __('Give the character a name.'),
            'appearance.required' => __('Describe how the character looks.'),
            'base_opacity.required' => __('Set how guarded the character is (0–100).'),
        ];
    }

    /**
     * Enforce the mode-dependent requirements and the one-player-per-story rule.
     *
     * @param  Validator  $validator  The current request validator.
     */
    protected function guardCharacterFields(Validator $validator): void
    {
        if ($this->boolean('is_player')) {
            $this->guardSinglePlayer($validator);

            return;
        }

        // NPC: folded_identity is required.
        $foldedIdentity = $this->input('folded_identity');

        if (! is_string($foldedIdentity) || trim($foldedIdentity) === '') {
            $validator->errors()->add('folded_identity', __('Folded identity is required for a non-player character.'));
        }

        // NPC: knowledge_boundary is mandatory even in minimal manual mode.
        if (! $this->hasKnowledgeBoundaryEntry()) {
            $validator->errors()->add('knowledge_boundary', __('Knowledge boundary is required: list at least one thing this character knows or does not know.'));
        }
    }

    /**
     * Reject a second player on the same story (excluding the bound character).
     *
     * @param  Validator  $validator  The current request validator.
     */
    private function guardSinglePlayer(Validator $validator): void
    {
        /** @var Story $story */
        $story = $this->route('story');
        $current = $this->route('character');

        $alreadyHasPlayer = $story->characters()
            ->where('is_player', true)
            ->when(
                $current instanceof Character,
                fn ($query) => $query->whereKeyNot($current->getKey()),
            )
            ->exists();

        if ($alreadyHasPlayer) {
            $validator->errors()->add('is_player', __('This story already has a player character — only one character can be the player.'));
        }
    }

    /**
     * Whether the knowledge boundary carries at least one non-empty entry.
     */
    private function hasKnowledgeBoundaryEntry(): bool
    {
        $knows = $this->input('knowledge_boundary.knows', []);
        $doesNotKnow = $this->input('knowledge_boundary.does_not_know', []);

        return collect(array_merge(
            is_array($knows) ? $knows : [],
            is_array($doesNotKnow) ? $doesNotKnow : [],
        ))
            ->map(fn ($entry): string => is_string($entry) ? trim($entry) : '')
            ->filter(fn (string $entry): bool => $entry !== '')
            ->isNotEmpty();
    }
}
