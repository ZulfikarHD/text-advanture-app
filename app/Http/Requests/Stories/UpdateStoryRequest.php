<?php

namespace App\Http\Requests\Stories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a story update (S-1.1.2).
 *
 * Slug collision detection excludes the story being updated. The route
 * model binding resolves the story under the owner scope, so a foreign
 * story resolves to 404 before validation runs.
 */
class UpdateStoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stories', 'slug')
                    ->where('user_id', $this->user()->getKey())
                    ->ignore($this->route('story')?->id),
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
