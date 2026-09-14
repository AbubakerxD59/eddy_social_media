<?php

namespace App\Http\Requests;

use App\Enums\MessageKind;
use App\Models\Message;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = $this->route('message');
            $trimmed = trim((string) $this->input('body'));

            if (! $message instanceof Message) {
                return;
            }

            if ($message->kind === MessageKind::Text && $trimmed === '') {
                $validator->errors()->add('body', 'Write a message.');
            }
        });
    }
}
