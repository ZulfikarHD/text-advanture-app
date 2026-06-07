<?php

namespace App\Http\Requests\Stories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates renaming an existing save (S-2.1.2).
 *
 * A rename carries a single required `name`; the save is resolved by scoped
 * route binding and authorization is asserted on the parent story in the
 * controller (`update` gate). Names carry no uniqueness constraint — two saves
 * may share a label harmlessly.
 */
class RenameSessionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Give this save a name.'),
            'name.max' => __('A save name may be at most 150 characters.'),
        ];
    }
}
