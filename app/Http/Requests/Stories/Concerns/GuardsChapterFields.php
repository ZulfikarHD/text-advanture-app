<?php

namespace App\Http\Requests\Stories\Concerns;

use App\Enums\PovMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation for minimal manual chapter requests (S-1.2.1).
 *
 * A chapter carries a `title` and a default POV mode (`pov_default`); its
 * `number` is system-managed (max + 1 per story), so it is never accepted from
 * the request. The store and update requests share these rules verbatim.
 */
trait GuardsChapterFields
{
    /**
     * The field rules common to creating and updating a chapter.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function chapterRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'pov_default' => ['required', Rule::enum(PovMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function chapterMessages(): array
    {
        return [
            'title.required' => __('Give the chapter a title.'),
            'pov_default.required' => __('Choose the chapter\'s default point of view.'),
        ];
    }
}
