<?php

namespace App\Http\Requests\Stories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates starting (forking) a new save (S-2.1.2).
 *
 * The fork itself is derived entirely from the server-authorized story; the
 * only client-supplied field is an optional `name`. When omitted, the service
 * falls back to the auto-derived "Playthrough N". Authorization is asserted on
 * the parent story in the controller (`update` gate), matching the sibling
 * authoring requests.
 */
class StartSessionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => __('A save name may be at most 150 characters.'),
        ];
    }
}
