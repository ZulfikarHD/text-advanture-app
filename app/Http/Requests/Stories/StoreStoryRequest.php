<?php

namespace App\Http\Requests\Stories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new story creation (S-1.1.1).
 *
 * Slug is optional: when omitted the service derives one from the title.
 * When provided it must be URL-safe and unique among the authenticated
 * author's stories. Authorization is handled by the `auth` middleware and
 * the StoryPolicy `create` gate.
 */
class StoreStoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stories', 'slug')
                    ->where('user_id', $this->user()->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('The slug must be lowercase letters, numbers, and hyphens only.'),
            'slug.unique' => __('That slug is already used by another of your stories.'),
        ];
    }
}
