<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit-and-commit decision on a review item (S-6.2.2).
 *
 * Carries the author's edited payload. Authorization is the `auth` middleware
 * plus the owner scope on route-model binding (a foreign item never resolves),
 * so no explicit `authorize()` is needed.
 */
class ReviewDecisionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payload.required' => __('Provide the edited content to commit.'),
            'payload.array' => __('The edited content must be a valid object.'),
        ];
    }
}
