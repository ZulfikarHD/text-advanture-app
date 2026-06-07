<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsCharacterFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a new minimal manual character (S-1.1.1 / S-1.1.2, ADR 0018 §2).
 *
 * A character is `{ name, appearance, base_opacity, is_player?, folded_identity?,
 * knowledge_boundary? }`. `name`, `appearance`, and `base_opacity` are always
 * required; a non-player (NPC) additionally requires `folded_identity` and a
 * non-empty `knowledge_boundary`, while a player carries appearance +
 * base_opacity only. Exactly one player is allowed per story. The mode-dependent
 * checks and the one-player rule run in {@see after()}. Authorization is asserted
 * on the parent story in the controller.
 */
class StoreCharacterRequest extends FormRequest
{
    use GuardsCharacterFields;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->characterRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->characterMessages();
    }

    /**
     * Run the mode-dependent and single-player guards after the field rules.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->guardCharacterFields($validator),
        ];
    }
}
