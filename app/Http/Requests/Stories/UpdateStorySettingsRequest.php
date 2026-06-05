<?php

namespace App\Http\Requests\Stories;

use App\Enums\LlmRole;
use App\Enums\PovMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a per-story settings save (S-1.2.1).
 *
 * Carries the default POV plus one row per engine {@see LlmRole}. A row's model
 * fields are only required when its `override` flag is set; an unset override
 * means the role falls back to the global default. Ownership is enforced in the
 * controller via the policy + the owner-scoped route binding.
 */
class UpdateStorySettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'default_pov' => ['required', Rule::enum(PovMode::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role' => ['required', Rule::enum(LlmRole::class)],
            'roles.*.override' => ['required', 'boolean'],
            'roles.*.model_slug' => ['nullable', 'required_if:roles.*.override,true', 'string', 'max:120'],
            'roles.*.temperature' => ['nullable', 'required_if:roles.*.override,true', 'numeric', 'min:0', 'max:2'],
            'roles.*.max_tokens' => ['nullable', 'required_if:roles.*.override,true', 'integer', 'min:1', 'max:200000'],
            'roles.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.model_slug.required_if' => __('Choose a model slug for every role you override.'),
            'roles.*.temperature.required_if' => __('Set a temperature for every role you override.'),
            'roles.*.temperature.max' => __('Temperature must be between 0 and 2.'),
            'roles.*.max_tokens.required_if' => __('Set a max-tokens value for every role you override.'),
        ];
    }
}
