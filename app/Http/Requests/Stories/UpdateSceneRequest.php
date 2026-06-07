<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsSceneFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates edits to an existing minimal manual scene (S-1.2.1).
 *
 * Shares the field rules and the present-cast / POV-anchor guards with
 * {@see StoreSceneRequest}. Authorization is asserted on the parent story in
 * the controller.
 */
class UpdateSceneRequest extends FormRequest
{
    use GuardsSceneFields;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->sceneRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->sceneMessages();
    }

    /**
     * Run the present-cast / POV-anchor guards after the field rules.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->guardSceneFields($validator),
        ];
    }
}
