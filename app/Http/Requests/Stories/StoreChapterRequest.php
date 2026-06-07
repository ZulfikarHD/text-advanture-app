<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsChapterFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new minimal manual chapter (S-1.2.1).
 *
 * A chapter is `{ title, pov_default }`; its `number` is system-managed.
 * Authorization is asserted on the parent story in the controller.
 */
class StoreChapterRequest extends FormRequest
{
    use GuardsChapterFields;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->chapterRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->chapterMessages();
    }
}
