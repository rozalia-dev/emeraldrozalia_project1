<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunicationTemplateAttachmentService
{
    public const MODES = ['none', 'file', 'link', 'file_and_link'];

    public function apply(Request $request, array $payload, ?CommunicationTemplate $existing = null): array
    {
        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $existingAttachment = $this->forTemplate($existing) ?? [];
        $mode = (string) $request->input('attachment_mode', $existingAttachment['mode'] ?? 'none');

        if (! in_array($mode, self::MODES, true)) {
            $mode = 'none';
        }

        unset($variables['_attachment']);

        if ($mode === 'none') {
            $payload['variables'] = $variables;

            return $payload;
        }

        $attachment = [
            'mode' => $mode,
            'label' => trim((string) $request->input('attachment_label', $existingAttachment['label'] ?? '')),
        ];

        if (in_array($mode, ['link', 'file_and_link'], true)) {
            $url = trim((string) $request->input('attachment_url', $existingAttachment['url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw ValidationException::withMessages([
                    'attachment_url' => 'A valid http:// or https:// link is required for this attachment mode.',
                ]);
            }
            $attachment['url'] = $url;
        }

        if (in_array($mode, ['file', 'file_and_link'], true)) {
            $file = $request->file('attachment_file');
            $removeExisting = $request->boolean('remove_attachment_file');

            if ($file) {
                $folder = 'communication/template-attachments/'.Str::uuid();
                $extension = strtolower((string) ($file->extension() ?: $file->getClientOriginalExtension()));
                $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
                $storedName = Str::uuid().'.'.$extension;
                $path = $file->storeAs($folder, $storedName, 'local');

                if (! is_string($path) || $path === '') {
                    throw ValidationException::withMessages([
                        'attachment_file' => 'The attachment could not be stored. Please try again.',
                    ]);
                }

                $attachment['file_path'] = $path;
                $attachment['file_name'] = Str::limit(basename((string) $file->getClientOriginalName()), 180, '');
                $attachment['mime_type'] = (string) ($file->getMimeType() ?: 'application/octet-stream');
                $attachment['size'] = (int) $file->getSize();
            } elseif (! $removeExisting && filled($existingAttachment['file_path'] ?? null)) {
                foreach (['file_path', 'file_name', 'mime_type', 'size'] as $key) {
                    if (array_key_exists($key, $existingAttachment)) {
                        $attachment[$key] = $existingAttachment[$key];
                    }
                }
            }

            if (blank($attachment['file_path'] ?? null)) {
                throw ValidationException::withMessages([
                    'attachment_file' => 'Choose a file for this attachment mode.',
                ]);
            }
        }

        if (($attachment['label'] ?? '') === '') {
            $attachment['label'] = (string) ($attachment['file_name'] ?? 'Open attachment');
        }

        $variables['_attachment'] = $attachment;
        $payload['variables'] = $variables;

        return $payload;
    }

    public function forTemplate(?CommunicationTemplate $template): ?array
    {
        if (! $template) {
            return null;
        }

        $variables = is_array($template->variables) ? $template->variables : [];
        $attachment = $variables['_attachment'] ?? null;

        return is_array($attachment) && in_array((string) ($attachment['mode'] ?? ''), self::MODES, true)
            ? $attachment
            : null;
    }

    public function assertStoredFileAvailable(array $attachment): string
    {
        $path = (string) ($attachment['file_path'] ?? '');
        if ($path === '' || ! str_starts_with($path, 'communication/template-attachments/')) {
            throw ValidationException::withMessages([
                'attachment_file' => 'The template attachment path is invalid.',
            ]);
        }

        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'attachment_file' => 'The template attachment file is missing from private storage.',
            ]);
        }

        return $path;
    }
}
