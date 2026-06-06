<?php

namespace App\Http\Requests\Stories;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new reveal-ledger entry (S-4.1.1, ADR 0013 §3).
 *
 * A reveal-ledger entry is `{ fact, reveal_chapter, character?, who_knows[], notes? }`.
 * `fact` and a `reveal_chapter` are required; `reveal_chapter` and the optional
 * "about" `character` must reference rows of THIS story (so a foreign chapter or
 * character can never bind to an entry). `who_knows` is a free-text list of
 * character slugs exempt from the reveal clamp — not existence-checked, since
 * characters are authored in a later phase. Authorization is asserted on the
 * parent story in the controller.
 */
class StoreRevealLedgerEntryRequest extends FormRequest
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
