<?php

namespace App\Http\Requests\Stories;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates a lorebook keyword match preview request (S-3.2.1, ADR 0013 §5).
 *
 * The author submits a sample excerpt and, optionally, the chapter they are
 * previewing at. `chapter_id` must reference a chapter of THIS story so a
 * foreign chapter can never drive the reveal-gate clamp. Authorization is
 * asserted on the parent story in the controller.
 *
 * Unlike the page-visit lorebook requests, this endpoint is consumed by a
 * standalone `useHttp` client, so {@see failedValidation()} forces a JSON 422 —
 * the app otherwise renders web redirects for validation errors outside
 * `api/*` (see `bootstrap/app.php` `shouldRenderJsonWhen`).
 */
class PreviewLorebookRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Story $story */
        $story = $this->route('story');

        return [
            'sample_text' => ['required', 'string', 'max:20000'],
            'chapter_id' => [
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
            'sample_text.required' => __('Paste some sample text to test which entries it triggers.'),
            'chapter_id.exists' => __('The selected chapter does not belong to this story.'),
        ];
    }

    /**
     * Force a JSON 422 so the standalone `useHttp` client receives field errors.
     *
     * @param  Validator  $validator  The failed validator.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => __('The given data was invalid.'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
