<?php

namespace App\Http\Requests\Stories\Concerns;

use App\Enums\PovMode;
use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for minimal manual scene requests (S-1.2.1).
 *
 * A scene carries its POV contract (`pov_mode`, `pov_anchor`, `tone`) and the
 * cast present in it. The present cast and the POV anchor are references to the
 * story's characters (by slug), so they are checked against the story in
 * {@see guardSceneFields()}: every present slug must belong to the story, and
 * the anchor must be both a story character and one of the present cast (the
 * viewpoint character has to be in the scene). The narrator's `POV_CONTRACT`
 * block (E4) reads these fields, hence the cross-check now.
 */
trait GuardsSceneFields
{
    /**
     * The field rules common to creating and updating a scene.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function sceneRules(): array
    {
        return [
            'pov_mode' => ['required', Rule::enum(PovMode::class)],
            'pov_anchor' => ['required', 'string', 'max:150'],
            'tone' => ['nullable', 'string', 'max:120'],
            'present_characters' => ['required', 'array', 'min:1'],
            'present_characters.*' => ['string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sceneMessages(): array
    {
        return [
            'pov_mode.required' => __('Choose how the scene is narrated.'),
            'pov_anchor.required' => __('Choose the scene\'s viewpoint character.'),
            'present_characters.required' => __('Add at least one character present in the scene.'),
            'present_characters.min' => __('Add at least one character present in the scene.'),
        ];
    }

    /**
     * Check the present cast and the POV anchor against the story's characters.
     *
     * @param  Validator  $validator  The current request validator.
     */
    protected function guardSceneFields(Validator $validator): void
    {
        /** @var Story $story */
        $story = $this->route('story');

        $storySlugs = $story->characters()->pluck('slug')->all();
        $present = array_values(array_filter(
            (array) $this->input('present_characters', []),
            fn ($slug): bool => is_string($slug),
        ));

        if (array_diff($present, $storySlugs) !== []) {
            $validator->errors()->add('present_characters', __('Every present character must belong to this story.'));
        }

        $anchor = $this->input('pov_anchor');

        if (! is_string($anchor) || $anchor === '') {
            return;
        }

        if (! in_array($anchor, $storySlugs, true)) {
            $validator->errors()->add('pov_anchor', __('The viewpoint character must belong to this story.'));

            return;
        }

        if (! in_array($anchor, $present, true)) {
            $validator->errors()->add('pov_anchor', __('The viewpoint character must be one of the present characters.'));
        }
    }
}
