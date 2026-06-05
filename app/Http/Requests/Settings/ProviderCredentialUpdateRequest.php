<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a provider API-key save (S-5.1.1).
 *
 * The key is required and length-bounded; `base_url` is an optional override of
 * the configured gateway URL. Authorization is handled by the `auth`
 * middleware - the credential is owner-scoped at the model layer.
 */
class ProviderCredentialUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['required', 'string', 'min:8', 'max:255'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_key.required' => __('Enter your provider API key.'),
            'api_key.min' => __('That key looks too short to be valid.'),
        ];
    }
}
