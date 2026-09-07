<?php

namespace App\Http\Requests;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreStoryRequest extends FormRequest
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
            'media' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user !== null && $user->stories()->active()->count() >= Story::MAX_ACTIVE_PER_USER) {
                $validator->errors()->add(
                    'media',
                    'You can have up to '.Story::MAX_ACTIVE_PER_USER.' live stories at a time.',
                );
            }

            $file = $this->file('media');

            if (! $file instanceof UploadedFile) {
                return;
            }

            $mime = (string) $file->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                if ($file->getSize() > 8 * 1024 * 1024) {
                    $validator->errors()->add('media', 'Images must be 8 MB or smaller.');
                }

                return;
            }

            if (str_starts_with($mime, 'video/')) {
                if ($file->getSize() > 50 * 1024 * 1024) {
                    $validator->errors()->add('media', 'Videos must be 50 MB or smaller.');
                }

                return;
            }

            $validator->errors()->add('media', 'Upload a photo or a video.');
        });
    }
}
