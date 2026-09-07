<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreSignalUploadRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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

            $validator->errors()->add('media', 'Upload an image or video.');
        });
    }
}
