<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsSceneFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a new minimal manual scene (S-1.2.1).
 *
 * A scene is `{ pov_mode, pov_anchor, tone?, present_characters }`; its `number`
 * is system-managed. The present-cast and anchor cross-checks against the
 * story's characters run in {@see after()}. Authorization is asserted on the
 * parent story in the controller.
 */
class StoreSceneRequest extends FormRequest
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
