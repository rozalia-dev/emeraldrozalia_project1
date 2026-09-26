<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunicationMailboxAttachmentService
{
    private const MAX_FILES = 5;

    public function validationRules(): array
    {
        return [
            'attachments' => ['sometimes', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,zip',
            ],
        ];
    }

    /** @param array<int, UploadedFile>|null $uploads
     *  @return array<int, array{id:string,name:string,mime_type:string,size:int,path:string}>
     */
    public function store(?array $uploads, string $conversationUuid, int $existingCount = 0): array
    {
        $uploads = array_values(array_filter($uploads ?? []));
        if (count($uploads) + $existingCount > self::MAX_FILES) {
            throw ValidationException::withMessages([
                'attachments' => 'A message can have no more than '.self::MAX_FILES.' attachments.',
            ]);
        }

        $stored = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                throw ValidationException::withMessages([
                    'attachments' => 'One of the selected attachments could not be uploaded. Please try again.',
                ]);
            }

            $extension = strtolower((string) ($upload->extension() ?: $upload->getClientOriginalExtension()));
            $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
            $id = (string) Str::uuid();
            $folder = 'communication/mailbox/'.$conversationUuid;
            $path = $upload->storeAs($folder, $id.'.'.$extension, 'local');

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'attachments' => 'An attachment could not be stored. Please try again.',
                ]);
            }

            $originalName = basename(str_replace('\\', '/', (string) $upload->getClientOriginalName()));
            $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'attachment.'.$extension;

            $stored[] = [
                'id' => $id,
                'name' => Str::limit($originalName, 180, ''),
                'mime_type' => (string) ($upload->getMimeType() ?: 'application/octet-stream'),
                'size' => (int) $upload->getSize(),
                'path' => $path,
            ];
        }

        return $stored;
    }

    public function find(array $attachments, string $attachmentId, string $conversationUuid): ?array
    {
        foreach ($attachments as $attachment) {
            if (! is_array($attachment) || ! hash_equals((string) ($attachment['id'] ?? ''), $attachmentId)) {
                continue;
            }

            $path = (string) ($attachment['path'] ?? '');
            if (! str_starts_with($path, 'communication/mailbox/'.$conversationUuid.'/')
                || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            return $attachment;
        }

        return null;
    }

    public function deleteConversationFiles(iterable $messages, array $conversationMetadata): void
    {
        $paths = [];
        foreach ($messages as $message) {
            foreach ((array) data_get($message->payload, 'attachments', []) as $attachment) {
                if (is_array($attachment) && filled($attachment['path'] ?? null)) {
                    $paths[] = (string) $attachment['path'];
                }
            }
        }
        foreach ((array) ($conversationMetadata['draft_attachments'] ?? []) as $attachment) {
            if (is_array($attachment) && filled($attachment['path'] ?? null)) {
                $paths[] = (string) $attachment['path'];
            }
        }

        foreach (array_unique($paths) as $path) {
            if (str_starts_with($path, 'communication/mailbox/') && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
