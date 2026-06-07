<?php

namespace App\Http\Requests\Stories;

use App\Http\Requests\Stories\Concerns\GuardsBeatFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates edits to an existing minimal manual beat (S-1.2.1).
 *
 * Shares the `goal` rule with {@see StoreBeatRequest}. Authorization is asserted
 * on the parent story in the controller.
 */
class UpdateBeatRequest extends FormRequest
{
    use GuardsBeatFields;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->beatRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->beatMessages();
    }
}
