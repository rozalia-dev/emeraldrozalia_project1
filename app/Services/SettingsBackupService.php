<?php

namespace App\Services;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SettingsBackupService
{
    public const FORMAT = 'emerald-rozalia.project1.settings';
    public const VERSION = 1;

    public function create(array $settings, string $type = 'Settings'): BackupRun
    {
        $startedAt = now();
        $uuid = (string) Str::uuid();
        $location = 'settings-backups/settings-'.now()->format('Ymd-His').'-'.$uuid.'.json';
        $payload = null;
        $checksum = null;
        $stored = false;
        $verified = false;
        $error = null;

        try {
            $document = [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'application' => 'Emerald Rozalia Project 1',
                'exported_at' => now()->toIso8601String(),
                'settings' => $settings,
            ];
            $payload = json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            $checksum = hash('sha256', $payload);
            $stored = Storage::disk('local')->put($location, $payload);
            if (! $stored) {
                throw new \RuntimeException('The private backup disk rejected the settings manifest.');
            }

            $readBack = Storage::disk('local')->get($location);
            $verified = hash_equals($checksum, hash('sha256', $readBack));
            if (! $verified) {
                throw new \RuntimeException('The settings manifest failed its read-back integrity check.');
            }
        } catch (Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 500, '');
            if ($stored) {
                Storage::disk('local')->delete($location);
            }
            $stored = false;
            $verified = false;
        }

        return BackupRun::create([
            'uuid' => $uuid,
            'type' => $type,
            'status' => $verified ? 'completed' : 'failed',
            'location' => $stored ? $location : null,
            'size_bytes' => $stored && $payload !== null ? strlen($payload) : null,
            'started_at' => $startedAt,
            'completed_at' => now(),
            'checksum' => $checksum,
            'verified_at' => $verified ? now() : null,
            'restore_status' => null,
            'restored_at' => null,
            'metadata' => [
                'scope' => 'settings',
                'sections' => count($settings),
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'checksum_algorithm' => 'sha256',
                'error' => $error,
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function readAndVerify(BackupRun $backup): array
    {
        if ($backup->status !== 'completed' || blank($backup->location) || blank($backup->checksum)) {
            $this->invalid('Only a verified completed backup can be restored.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($backup->location)) {
            $this->invalid('The backup manifest is no longer available on the private storage disk.');
        }

        try {
            $payload = $disk->get($backup->location);
        } catch (Throwable) {
            $this->invalid('The backup manifest could not be read from the private storage disk.');
        }

        if (! hash_equals((string) $backup->checksum, hash('sha256', $payload))) {
            $this->invalid('The backup manifest checksum does not match the recorded checksum.');
        }

        try {
            $document = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->invalid('The backup manifest is not valid JSON.');
        }

        if (($document['format'] ?? null) !== self::FORMAT || (int) ($document['version'] ?? 0) !== self::VERSION) {
            $this->invalid('The backup manifest format is not supported by this Project 1 release.');
        }

        $settings = $document['settings'] ?? null;
        if (! is_array($settings)) {
            $this->invalid('The backup manifest does not contain a settings object.');
        }

        return $settings;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['backup' => $message]);
    }
}
