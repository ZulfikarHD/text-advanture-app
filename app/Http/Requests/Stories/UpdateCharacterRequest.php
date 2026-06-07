<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsCharacterFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a minimal manual character update (S-1.1.1 / S-1.1.2, ADR 0018 §2).
 *
 * Same shape as creation: `name`, `appearance`, and `base_opacity` are always
 * required; a non-player additionally requires `folded_identity` and a non-empty
 * `knowledge_boundary`. The one-player-per-story rule excludes the bound
 * character, so editing the existing player (or switching which character is the
 * player) is allowed. The character is resolved (and scoped to the story) by
 * route-model binding before this runs.
 */
class UpdateCharacterRequest extends FormRequest
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
