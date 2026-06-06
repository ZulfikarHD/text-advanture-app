<?php

namespace App\Http\Requests\Stories;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new lorebook entry (S-3.1.1, ADR 0013 §5).
 *
 * A lorebook entry is `{ title?, keywords, content, min_reveal_chapter? }`. At
 * least one keyword and content are required; an optional `min_reveal_chapter`
 * must reference a chapter of THIS story (so a foreign chapter can never gate an
 * entry). Authorization is asserted on the parent story in the controller.
 */
class StoreLorebookEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Story $story */
        $story = $this->route('story');

        return [
            'title' => ['nullable', 'string', 'max:200'],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:10000'],
            'min_reveal_chapter_id' => [
                'nullable',
                'integer',
                Rule::exists('chapters', 'id')->where('story_id', $story->getKey()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keywords.required' => __('Add at least one keyword so the entry can be matched at runtime.'),
            'keywords.min' => __('Add at least one keyword so the entry can be matched at runtime.'),
            'content.required' => __('Lorebook content is required.'),
            'min_reveal_chapter_id.exists' => __('The selected reveal chapter does not belong to this story.'),
        ];
    }
}
