<?php

namespace App\Http\Requests\Stories;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a reveal-ledger entry update (S-4.1.1, ADR 0013 §3).
 *
 * Same shape as creation: `fact` and a `reveal_chapter` are required, and the
 * `reveal_chapter` plus the optional "about" `character` must belong to this
 * story. The entry itself is resolved (and scoped to the story) by route-model
 * binding before this runs.
 */
class UpdateRevealLedgerEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Story $story */
        $story = $this->route('story');

        return [
            'fact' => ['required', 'string', 'max:255'],
            'reveal_chapter_id' => [
                'required',
                'integer',
                Rule::exists('chapters', 'id')->where('story_id', $story->getKey()),
            ],
            'character_id' => [
                'nullable',
                'integer',
                Rule::exists('characters', 'id')->where('story_id', $story->getKey()),
            ],
            'who_knows' => ['nullable', 'array'],
            'who_knows.*' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fact.required' => __('Name the secret so it can be tracked.'),
            'reveal_chapter_id.required' => __('Choose the chapter where this fact becomes known.'),
            'reveal_chapter_id.exists' => __('The selected reveal chapter does not belong to this story.'),
            'character_id.exists' => __('The selected character does not belong to this story.'),
        ];
    }
}
