<?php

namespace App\Http\Requests;

use App\Enums\SignalType;
use App\Models\SignalUpload;
use App\Support\HtmlBody;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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

        if (filled($this->input('parent_id'))) {
            $this->merge(['type' => SignalType::Drop->value]);
        }

        if ($this->exists('body')) {
            $this->merge([
                'body' => HtmlBody::sanitize($this->input('body')),
            ]);
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
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:12000'],
            'budget' => ['nullable', 'string', 'max:80'],
            'timeline' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'skills' => ['nullable', 'string', 'max:255'],
            'project_value' => ['nullable', 'string', 'max:80'],
            'trades' => ['nullable', 'string', 'max:255'],
            'poll_options' => ['nullable', 'array', 'max:4'],
            'poll_options.*' => ['nullable', 'string', 'max:80'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'link_title' => ['nullable', 'string', 'max:255'],
            'link_description' => ['nullable', 'string', 'max:500'],
            'link_image' => ['nullable', 'url', 'max:2048'],
            'media' => ['nullable', 'array', 'max:6'],
            'media.*' => ['file'],
            'media_ids' => ['nullable', 'array', 'max:6'],
            'media_ids.*' => ['string', 'size:12'],
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
                SignalType::Drop => $this->validateDrop($validator),
                SignalType::Need => $this->validateNeed($validator),
                SignalType::Opportunity => $this->validateOpportunity($validator),
                SignalType::Poll => $this->validatePoll($validator),
            };

            $this->validateMedia($validator, $type);
            $this->validateLink($validator, $type);
            $this->validateLocation($validator, $type);

            if (mb_strlen(HtmlBody::plainText(is_string($this->input('body')) ? $this->input('body') : null)) > 2000) {
                $validator->errors()->add('body', 'Keep it under 2000 characters.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function pollOptions(): array
    {
        $options = $this->input('poll_options', []);

        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $option): string => trim((string) $option), $options),
        ));
    }

    /**
     * @return list<string>
     */
    public function csvList(string $key): array
    {
        $value = $this->input($key);

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    private function validateDrop(Validator $validator): void
    {
        $hasBody = filled($this->input('body'));
        $media = $this->file('media', []);
        $hasMedia = $media instanceof UploadedFile || (is_array($media) && $media !== []);
        $hasUploads = is_array($this->input('media_ids')) && $this->input('media_ids') !== [];
        $hasLink = filled($this->input('link_url'));

        if ($hasBody || $hasMedia || $hasUploads || $hasLink) {
            return;
        }

        $validator->errors()->add(
            'body',
            filled($this->input('parent_id'))
                ? 'A reply needs some text.'
                : 'A drop needs some text, media, or a link.',
        );
    }

    private function validateNeed(Validator $validator): void
    {
        if (blank($this->input('title'))) {
            $validator->errors()->add('title', 'Give this need a title.');
        }
    }

    private function validateOpportunity(Validator $validator): void
    {
        if (blank($this->input('title'))) {
            $validator->errors()->add('title', 'Give this opportunity a title.');
        }
    }

    private function validatePoll(Validator $validator): void
    {
        if (blank($this->input('body'))) {
            $validator->errors()->add('body', 'Ask a question for this poll.');
        }

        if (count($this->pollOptions()) < 2) {
            $validator->errors()->add('poll_options', 'Add at least two poll options.');
        }
    }

    private function validateMedia(Validator $validator, SignalType $type): void
    {
        /** @var array<int, UploadedFile>|UploadedFile|null $files */
        $files = $this->file('media', []);

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files)) {
            $files = [];
        }

        $ids = $this->input('media_ids', []);
        $ids = is_array($ids) ? array_values(array_filter($ids, fn (mixed $id): bool => is_string($id) && $id !== '')) : [];

        if ($files === [] && $ids === []) {
            return;
        }

        if (! $type->allowsMedia()) {
            $validator->errors()->add('media', 'Polls cannot include media.');

            return;
        }

        if (count($files) + count($ids) > 6) {
            $validator->errors()->add('media', 'You can attach up to 6 files.');

            return;
        }

        foreach ($files as $index => $file) {
            $mime = (string) $file->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                if ($file->getSize() > 8 * 1024 * 1024) {
                    $validator->errors()->add("media.{$index}", 'Images must be 8 MB or smaller.');
                }

                continue;
            }

            if (str_starts_with($mime, 'video/')) {
                if ($file->getSize() > 50 * 1024 * 1024) {
                    $validator->errors()->add("media.{$index}", 'Videos must be 50 MB or smaller.');
                }

                continue;
            }

            $validator->errors()->add("media.{$index}", 'Upload an image or video.');
        }

        if ($ids === []) {
            return;
        }

        $owned = SignalUpload::query()
            ->where('user_id', $this->user()?->id)
            ->whereIn('public_id', $ids)
            ->count();

        if ($owned !== count(array_unique($ids))) {
            $validator->errors()->add('media_ids', 'One or more uploads are invalid.');
        }
    }

    private function validateLink(Validator $validator, SignalType $type): void
    {
        if (blank($this->input('link_url'))) {
            return;
        }

        if (! $type->allowsLink()) {
            $validator->errors()->add('link_url', 'Links can only be attached to drops.');
        }
    }

    private function validateLocation(Validator $validator, SignalType $type): void
    {
        $hasLatitude = $this->filled('latitude');
        $hasLongitude = $this->filled('longitude');

        if ($hasLatitude !== $hasLongitude) {
            $validator->errors()->add('location', 'Pick a place from the suggestions.');
        }

        if (! in_array($type, [SignalType::Need, SignalType::Opportunity], true)
            && ($this->filled('location') || $hasLatitude || $hasLongitude || $this->filled('place_id'))) {
            $validator->errors()->add('location', 'Location can only be attached to needs and opportunities.');
        }
    }
}
