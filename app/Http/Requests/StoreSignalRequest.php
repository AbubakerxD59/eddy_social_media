<?php

namespace App\Http\Requests;

use App\Enums\SignalType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('parent_id'))) {
            $this->merge(['parent_id' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SignalType::class)],
            'parent_id' => ['nullable', 'string', 'size:12', 'exists:signals,public_id'],
            'body' => ['nullable', 'string', 'max:2000'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'link_title' => ['nullable', 'string', 'max:255'],
            'link_description' => ['nullable', 'string', 'max:500'],
            'link_image' => ['nullable', 'url', 'max:2048'],
            'media' => ['nullable', 'array', 'max:6'],
            'media.*' => ['file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = SignalType::tryFrom((string) $this->input('type'));

            if ($type === null) {
                return;
            }

            match ($type) {
                SignalType::Quote => $this->validateQuote($validator),
                SignalType::Images => $this->validateImages($validator),
                SignalType::Video => $this->validateVideo($validator),
                SignalType::Link => $this->validateLink($validator),
            };
        });
    }

    private function validateQuote(Validator $validator): void
    {
        if (blank($this->input('body'))) {
            $validator->errors()->add('body', 'A quote needs some text.');
        }
    }

    private function validateImages(Validator $validator): void
    {
        $files = $this->file('media', []);

        if ($files === []) {
            $validator->errors()->add('media', 'Add at least one image.');

            return;
        }

        foreach ($files as $index => $file) {
            if (! $file->isValid() || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
                $validator->errors()->add("media.{$index}", 'Each file must be an image.');
            }

            if ($file->getSize() > 8 * 1024 * 1024) {
                $validator->errors()->add("media.{$index}", 'Images must be 8 MB or smaller.');
            }
        }
    }

    private function validateVideo(Validator $validator): void
    {
        $files = $this->file('media', []);

        if (count($files) !== 1) {
            $validator->errors()->add('media', 'A video signal needs exactly one video.');

            return;
        }

        $file = $files[0];
        $mime = (string) $file->getMimeType();

        if (! str_starts_with($mime, 'video/')) {
            $validator->errors()->add('media.0', 'Upload a video file.');
        }

        if ($file->getSize() > 50 * 1024 * 1024) {
            $validator->errors()->add('media.0', 'Videos must be 50 MB or smaller.');
        }
    }

    private function validateLink(Validator $validator): void
    {
        if (blank($this->input('link_url'))) {
            $validator->errors()->add('link_url', 'Paste a link to preview.');
        }
    }
}
