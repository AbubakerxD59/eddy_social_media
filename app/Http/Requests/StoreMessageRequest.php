<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreMessageRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @var list<string>
     */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * @var list<string>
     */
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v', 'ogg'];

    /**
     * @var list<string>
     */
    private const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * @var list<string>
     */
    private const DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

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
            'reply_to_id' => ['nullable', 'string', 'size:8', 'exists:messages,public_id'],
            'attachment' => ['nullable', 'file'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('attachments');

        if (is_array($files)) {
            return array_values(array_filter(
                $files,
                fn (mixed $file): bool => $file instanceof UploadedFile,
            ));
        }

        $single = $this->file('attachment');

        return $single instanceof UploadedFile ? [$single] : [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = trim((string) $this->input('body'));
            $files = $this->uploadedFiles();

            if ($body === '' && $files === []) {
                $validator->errors()->add('body', 'Write a message or attach a file.');

                return;
            }

            $replyKey = trim((string) $this->input('reply_to_id'));

            if ($replyKey !== '') {
                $conversation = $this->route('conversation');
                $reply = Message::query()->where('public_id', $replyKey)->first();

                if (
                    ! $conversation instanceof Conversation
                    || $reply === null
                    || $reply->conversation_id !== $conversation->id
                ) {
                    $validator->errors()->add('reply_to_id', 'You can only reply to a message in this chat.');
                }
            }

            foreach ($files as $index => $file) {
                $this->validateUploadedFile($validator, $file, $index);
            }
        });
    }

    private function validateUploadedFile(Validator $validator, UploadedFile $file, int $index): void
    {
        $key = "attachments.{$index}";
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $size = (int) $file->getSize();

        if ($this->isAllowedImage($mime, $extension)) {
            if ($size > 8 * 1024 * 1024) {
                $validator->errors()->add($key, 'Images must be 8 MB or smaller.');
            }

            return;
        }

        if ($this->isAllowedVideo($mime, $extension)) {
            if ($size > 50 * 1024 * 1024) {
                $validator->errors()->add($key, 'Videos must be 50 MB or smaller.');
            }

            return;
        }

        if ($this->isAllowedDocument($mime, $extension)) {
            if ($size > 20 * 1024 * 1024) {
                $validator->errors()->add($key, 'Files must be 20 MB or smaller.');
            }

            return;
        }

        $validator->errors()->add(
            $key,
            'You can only send photos, videos, PDFs, Word, or Excel files.',
        );
    }

    private function isAllowedImage(string $mime, string $extension): bool
    {
        return in_array($mime, self::IMAGE_MIMES, true)
            || in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    private function isAllowedVideo(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'video/')
            || in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    private function isAllowedDocument(string $mime, string $extension): bool
    {
        return in_array($mime, self::DOCUMENT_MIMES, true)
            || in_array($extension, self::DOCUMENT_EXTENSIONS, true);
    }
}
