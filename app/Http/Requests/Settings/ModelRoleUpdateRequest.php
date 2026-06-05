<?php

namespace App\Http\Requests\Settings;

use App\Enums\LlmRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a model-role mapping save (S-5.2.2).
 *
 * Accepts a list of role rows; each pairs an engine {@see LlmRole} with a model
 * slug and the two common tunable params (temperature, max tokens). Authorization
 * is the `auth` middleware - the edited profiles are the app-wide global defaults.
 */
class ModelRoleUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role' => ['required', Rule::enum(LlmRole::class)],
            'roles.*.model_slug' => ['required', 'string', 'max:120'],
            'roles.*.temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'roles.*.max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'roles.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.model_slug.required' => __('Choose a model slug for every role.'),
            'roles.*.temperature.max' => __('Temperature must be between 0 and 2.'),
        ];
    }
}
