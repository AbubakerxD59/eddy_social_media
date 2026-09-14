<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTalentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isExplorer() || $user->isTalent());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:160'],
            'bio' => ['required', 'string', 'max:1200'],
            'skills' => ['required', 'string', 'max:2000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->user()?->accountType() === UserType::Business) {
                $validator->errors()->add('headline', __('Business accounts cannot convert to talent.'));
            }
        });
    }
}
