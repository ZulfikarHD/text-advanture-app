<?php

namespace App\Http\Requests\Stories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the player's written contribution at a player moment (S-5.1.1).
 *
 * The only client-supplied field is the prose the player writes back to the
 * narrator; the save and its loop position come from the server-authorized
 * route binding. Authorization is asserted on the parent story in the
 * controller (`update` gate), matching the sibling save-write requests.
 */
class SubmitPlayerInputRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => __('Write something for your character before continuing.'),
            'content.max' => __('Your input may be at most 5000 characters.'),
        ];
    }
}
