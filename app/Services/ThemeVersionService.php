<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ThemeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ThemeVersionService
{
    public const ENVIRONMENTS = ['production', 'staging', 'development'];

    public const DEFAULT_TOKENS = [
        'colors' => [
            'primary' => '#075b2f',
            'secondary' => '#0b1711',
            'accent' => '#7fbd42',
            'surface' => '#ffffff',
            'surface_muted' => '#f3f6f2',
            'text' => '#0b1711',
            'text_muted' => '#5c6c62',
            'border' => '#d9e3db',
            'focus' => '#7fbd42',
            'success' => '#16784a',
            'warning' => '#b7791f',
            'danger' => '#b42318',
            'info' => '#2676cc',
        ],
        'typography' => [
            'font_family' => 'Inter, Arial, sans-serif',
            'heading_family' => 'Georgia, serif',
            'base_size' => '16px',
            'heading_weight' => 700,
        ],
        'spacing' => [
            'unit' => '4px',
            'section_y' => '64px',
            'container_max' => '1280px',
            'radius' => '12px',
        ],
        'controls' => [
            'button_radius' => '10px',
            'input_radius' => '8px',
            'button_height' => '44px',
        ],
        'motion' => [
            'enabled' => true,
            'duration_ms' => 180,
            'easing' => 'ease-out',
            'reduced_motion' => true,
        ],
        'breakpoints' => [
            'mobile' => '640px',
            'tablet' => '1024px',
            'desktop' => '1280px',
        ],
        'variants' => [
            'button' => 'emerald',
            'card' => 'soft',
            'header' => 'wordmark',
        ],
    ];

    public const DEFAULT_ASSET_REFERENCES = [
        'header_logo' => [
            'path' => '/assets/logo/logo_one_line.png',
            'role' => 'approved_header_logo',
            'status' => 'approved',
        ],
        'footer_logo' => [
            'path' => '/assets/logo/logo_two_line.png',
            'role' => 'approved_footer_logo',
            'status' => 'approved',
        ],
    ];

    private const COLOR_PATHS = [
        'colors.primary', 'colors.secondary', 'colors.accent', 'colors.surface',
        'colors.surface_muted', 'colors.text', 'colors.text_muted', 'colors.border',
        'colors.focus', 'colors.success', 'colors.warning', 'colors.danger', 'colors.info',
    ];

    private const DIMENSION_PATHS = [
        'typography.base_size', 'spacing.unit', 'spacing.section_y', 'spacing.container_max',
        'spacing.radius', 'controls.button_radius', 'controls.input_radius', 'controls.button_height',
        'breakpoints.mobile', 'breakpoints.tablet', 'breakpoints.desktop',
    ];

    private const INTEGER_PATHS = ['typography.heading_weight', 'motion.duration_ms'];

    private const BOOLEAN_PATHS = ['motion.enabled', 'motion.reduced_motion'];

    private const STRING_PATHS = [
        'typography.font_family', 'typography.heading_family', 'motion.easing',
        'variants.button', 'variants.card', 'variants.header',
    ];

    public function defaults(): array
    {
        return self::DEFAULT_TOKENS;
    }

    public function defaultAssetReferences(): array
    {
        return self::DEFAULT_ASSET_REFERENCES;
    }

    /** @return Builder<ThemeVersion> */
    public function query(?Company $company = null, string $environment = 'production', string $locale = 'en'): Builder
    {
        return ThemeVersion::query()
            ->withoutGlobalScopes()
            ->where('scope', 'public')
            ->where('environment', $environment)
            ->where('locale', $locale)
            ->when(
                $company,
                fn (Builder $query): Builder => $query->where('company_id', $company->getKey()),
                fn (Builder $query): Builder => $query->whereNull('company_id'),
            );
    }

    public function currentCompany(): ?Company
    {
        return app(TenantContext::class)->company();
    }

    public function currentEnvironment(?Request $request = null): string
    {
        $environment = (string) ($request?->query('environment') ?: config('app.theme_environment', 'production'));

        return in_array($environment, self::ENVIRONMENTS, true) ? $environment : 'production';
    }

    public function currentLocale(): string
    {
        return (string) (app()->getLocale() ?: 'en');
    }

    public function versions(?Company $company = null, ?string $environment = null, ?string $locale = null)
    {
        $company ??= $this->currentCompany();
        $environment ??= 'production';
        $locale ??= $this->currentLocale();

        return $this->query($company, $environment, $locale)->latest('version')->get();
    }

    public function publicSnapshot(?Company $company = null, string $environment = 'production', ?string $locale = null): array
    {
        $company ??= $this->currentCompany();
        $locale ??= $this->currentLocale();
        $theme = $this->query($company, $environment, $locale)
            ->where('status', ThemeVersion::STATUS_ACTIVE)
            ->latest('version')
            ->first();

        if (! $theme) {
            return [
                'meta' => [
                    'source' => 'default-theme-fallback',
                    'uuid' => null,
                    'version' => 0,
                    'status' => 'fallback',
                    'environment' => $environment,
                    'locale' => $locale,
                ],
                'tokens' => self::DEFAULT_TOKENS,
                'assets' => self::DEFAULT_ASSET_REFERENCES,
            ];
        }

        return [
            'meta' => [
                'source' => 'active-theme-version',
                'uuid' => $theme->uuid,
                'version' => (int) $theme->version,
                'status' => $theme->status,
                'environment' => $theme->environment,
                'locale' => $theme->locale,
                'activated_at' => $theme->activated_at?->toIso8601String(),
            ],
            'tokens' => $this->mergeTokens($theme->token_payload ?? []),
            'assets' => $this->approvedAssetReferences($theme->asset_references),
        ];
    }

    public function createDraft(array $attributes, ?User $user = null): ThemeVersion
    {
        $company = $attributes['company'] ?? $this->currentCompany();
        $environment = (string) ($attributes['environment'] ?? 'production');
        $locale = (string) ($attributes['locale'] ?? $this->currentLocale());
        $tokens = $this->validatedTokens($attributes['tokens'] ?? self::DEFAULT_TOKENS);
        $userId = $user?->getKey() ?? auth()->id();

        abort_unless(in_array($environment, self::ENVIRONMENTS, true), 422, 'Unsupported theme environment.');

        return DB::transaction(function () use ($attributes, $company, $environment, $locale, $tokens, $userId): ThemeVersion {
            $version = ((int) $this->query($company, $environment, $locale)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version')) + 1;

            $theme = ThemeVersion::create([
                'company_id' => $company?->getKey(),
                'name' => trim((string) ($attributes['name'] ?? 'Emerald Rozalia Theme')),
                'scope' => 'public',
                'environment' => $environment,
                'locale' => $locale,
                'version' => $version,
                'status' => ThemeVersion::STATUS_DRAFT,
                'token_payload' => $tokens,
                'asset_references' => self::DEFAULT_ASSET_REFERENCES,
                'created_by' => $userId,
                'notes' => $attributes['notes'] ?? null,
            ]);

            AuditTrail::record('settings.theme.draft_created', $theme, null, $this->auditState($theme));

            return $theme;
        });
    }

    public function updateDraft(ThemeVersion $theme, array $attributes, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only draft themes can be edited.');
        $tokens = $this->validatedTokens($attributes['tokens'] ?? $theme->token_payload ?? self::DEFAULT_TOKENS);
        $before = $this->auditState($theme);

        $theme->update([
            'name' => trim((string) ($attributes['name'] ?? $theme->name)),
            'token_payload' => $tokens,
            'validation_errors' => null,
            'validated_at' => null,
            'validated_by' => null,
            'notes' => $attributes['notes'] ?? $theme->notes,
        ]);

        AuditTrail::record('settings.theme.draft_updated', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function validateTheme(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only draft themes can be validated.');
        $errors = $this->tokenErrors($theme->token_payload ?? []);
        $before = $this->auditState($theme);

        if ($errors !== []) {
            $theme->update(['validation_errors' => $errors, 'validated_at' => null, 'validated_by' => null]);
            AuditTrail::record('settings.theme.validation_failed', $theme, $before, $this->auditState($theme->fresh()));
            throw ValidationException::withMessages($errors);
        }

        $theme->update([
            'validation_errors' => null,
            'validated_at' => now(),
            'validated_by' => $user?->getKey() ?? auth()->id(),
        ]);
        AuditTrail::record('settings.theme.validated', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function submitForApproval(ThemeVersion $theme): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only a draft theme can be submitted.');
        abort_unless($theme->validated_at, 409, 'Validate the theme before requesting approval.');

        return $this->transition($theme, ThemeVersion::STATUS_PENDING_APPROVAL, 'settings.theme.submitted');
    }

    public function approve(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_PENDING_APPROVAL, 409, 'Only pending themes can be approved.');
        $before = $this->auditState($theme);
        $theme->update([
            'status' => ThemeVersion::STATUS_APPROVED,
            'approved_by' => $user?->getKey() ?? auth()->id(),
            'approved_at' => now(),
        ]);
        AuditTrail::record('settings.theme.approved', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function activate(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_APPROVED, 409, 'Approve the theme before activation.');

        return DB::transaction(function () use ($theme, $user): ThemeVersion {
            $current = $this->query($theme->company, $theme->environment, $theme->locale)
                ->where('status', ThemeVersion::STATUS_ACTIVE)
                ->whereKeyNot($theme->getKey())
                ->lockForUpdate()
                ->first();

            if ($current) {
                $currentBefore = $this->auditState($current);
                $current->update(['status' => ThemeVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('settings.theme.superseded', $current, $currentBefore, $this->auditState($current->fresh()));
            }

            $before = $this->auditState($theme);
            $theme->update([
                'status' => ThemeVersion::STATUS_ACTIVE,
                'activated_by' => $user?->getKey() ?? auth()->id(),
                'activated_at' => now(),
            ]);
            AuditTrail::record('settings.theme.activated', $theme, $before, $this->auditState($theme->fresh()));

            return $theme->fresh();
        });
    }

    public function disable(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless(in_array($theme->status, [ThemeVersion::STATUS_ACTIVE, ThemeVersion::STATUS_APPROVED], true), 409, 'This theme cannot be disabled from its current state.');
        $before = $this->auditState($theme);
        $theme->update([
            'status' => ThemeVersion::STATUS_DISABLED,
            'disabled_by' => $user?->getKey() ?? auth()->id(),
            'disabled_at' => now(),
        ]);
        AuditTrail::record('settings.theme.disabled', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function rollback(ThemeVersion $target, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($target);
        abort_unless($target->status !== ThemeVersion::STATUS_DRAFT && $target->status !== ThemeVersion::STATUS_DISABLED, 409, 'Only an approved, active or superseded theme can be rolled back.');

        return DB::transaction(function () use ($target, $user): ThemeVersion {
            $current = $this->query($target->company, $target->environment, $target->locale)
                ->where('status', ThemeVersion::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($current && $current->is($target)) {
                $target = $this->query($target->company, $target->environment, $target->locale)
                    ->where('version', '<', $current->version)
                    ->whereIn('status', [ThemeVersion::STATUS_SUPERSEDED, ThemeVersion::STATUS_APPROVED])
                    ->latest('version')
                    ->lockForUpdate()
                    ->first();
                abort_unless($target, 409, 'There is no prior theme version available for rollback.');
            }

            $rollbackBefore = null;
            if ($current) {
                $currentBefore = $this->auditState($current);
                $rollbackBefore = $currentBefore;
                $current->update(['status' => ThemeVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('settings.theme.superseded', $current, $currentBefore, $this->auditState($current->fresh()));
            }

            $version = ((int) $this->query($target->company, $target->environment, $target->locale)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version')) + 1;
            $userId = $user?->getKey() ?? auth()->id();
            $rollback = ThemeVersion::create([
                'company_id' => $target->company_id,
                'name' => 'Rollback: '.$target->name,
                'scope' => $target->scope,
                'environment' => $target->environment,
                'locale' => $target->locale,
                'version' => $version,
                'status' => ThemeVersion::STATUS_ACTIVE,
                'token_payload' => $this->mergeTokens($target->token_payload ?? []),
                'asset_references' => $this->approvedAssetReferences($target->asset_references),
                'source_version_uuid' => $target->uuid,
                'created_by' => $userId,
                'validated_by' => $userId,
                'validated_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'activated_by' => $userId,
                'activated_at' => now(),
                'rolled_back_by' => $userId,
                'rolled_back_at' => now(),
                'notes' => 'Created from an approved prior theme version.',
            ]);
            AuditTrail::record('settings.theme.rolled_back', $rollback, $rollbackBefore, $this->auditState($rollback));

            return $rollback;
        });
    }

    public function auditState(ThemeVersion $theme): array
    {
        return [
            'uuid' => $theme->uuid,
            'company_id' => $theme->company_id,
            'name' => $theme->name,
            'scope' => $theme->scope,
            'environment' => $theme->environment,
            'locale' => $theme->locale,
            'version' => (int) $theme->version,
            'status' => $theme->status,
            'token_payload' => $this->mergeTokens($theme->token_payload ?? []),
            'asset_references' => $this->approvedAssetReferences($theme->asset_references),
            'source_version_uuid' => $theme->source_version_uuid,
            'validated_at' => $theme->validated_at?->toIso8601String(),
            'approved_at' => $theme->approved_at?->toIso8601String(),
            'activated_at' => $theme->activated_at?->toIso8601String(),
        ];
    }

    private function transition(ThemeVersion $theme, string $status, string $auditAction): ThemeVersion
    {
        $before = $this->auditState($theme);
        $theme->update(['status' => $status]);
        AuditTrail::record($auditAction, $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    private function assertCurrentContext(ThemeVersion $theme): void
    {
        $companyId = $this->currentCompany()?->getKey();

        abort_unless((int) $theme->company_id === (int) $companyId, 404);
    }

    private function mergeTokens(array $tokens): array
    {
        return array_replace_recursive(self::DEFAULT_TOKENS, $tokens);
    }

    private function approvedAssetReferences(?array $assets): array
    {
        // Batch 19 will add the UUID-backed media picker. Until then, the only
        // publishable references are these approved repository assets.
        return self::DEFAULT_ASSET_REFERENCES;
    }

    private function validatedTokens(array $tokens): array
    {
        $tokens = $this->coerceTokens($tokens);
        $errors = $this->tokenErrors($tokens);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->mergeTokens($tokens);
    }

    /** @return array<string, string> */
    private function tokenErrors(array $tokens): array
    {
        $errors = [];
        $tokens = $this->coerceTokens($tokens);
        $this->unknownTokenErrors($tokens, self::DEFAULT_TOKENS, '', $errors);
        $merged = $this->mergeTokens($tokens);

        foreach (self::COLOR_PATHS as $path) {
            if (! is_string(data_get($merged, $path)) || ! preg_match('/\A#[0-9a-fA-F]{6}\z/', (string) data_get($merged, $path))) {
                $errors[$path] = 'Use a six-digit hexadecimal colour such as #075b2f.';
            }
        }
        foreach (self::DIMENSION_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! is_string($value) || ! preg_match('/\A\d{1,4}px\z/', $value)) {
                $errors[$path] = 'Use a pixel value such as 16px.';
            }
        }
        foreach (self::INTEGER_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                $errors[$path] = 'Use a whole number.';
            }
        }
        foreach (self::BOOLEAN_PATHS as $path) {
            if (! is_bool(data_get($merged, $path))) {
                $errors[$path] = 'Use a boolean value.';
            }
        }
        foreach (self::STRING_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! is_string($value) || trim($value) === '' || strlen($value) > 160) {
                $errors[$path] = 'Use a non-empty value no longer than 160 characters.';
            }
        }

        $weight = (int) data_get($merged, 'typography.heading_weight');
        if ($weight < 400 || $weight > 900 || $weight % 100 !== 0) {
            $errors['typography.heading_weight'] = 'Heading weight must be between 400 and 900.';
        }
        $duration = (int) data_get($merged, 'motion.duration_ms');
        if ($duration < 0 || $duration > 1000) {
            $errors['motion.duration_ms'] = 'Motion duration must be between 0 and 1000 milliseconds.';
        }

        $mobile = (int) rtrim((string) data_get($merged, 'breakpoints.mobile'), 'px');
        $tablet = (int) rtrim((string) data_get($merged, 'breakpoints.tablet'), 'px');
        $desktop = (int) rtrim((string) data_get($merged, 'breakpoints.desktop'), 'px');
        if (! ($mobile < $tablet && $tablet < $desktop)) {
            $errors['breakpoints'] = 'Breakpoints must be ordered mobile, tablet, then desktop.';
        }

        return $errors;
    }

    private function coerceTokens(array $tokens): array
    {
        foreach (self::BOOLEAN_PATHS as $path) {
            if (! array_key_exists($path, $this->flattenPaths($tokens))) {
                continue;
            }

            $value = data_get($tokens, $path);
            if (is_bool($value)) {
                continue;
            }

            if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                data_set($tokens, $path, filter_var($value, FILTER_VALIDATE_BOOLEAN));
            }
        }

        return $tokens;
    }

    private function flattenPaths(array $input, string $prefix = ''): array
    {
        $paths = [];
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $paths[$path] = true;
            if (is_array($value)) {
                $paths += $this->flattenPaths($value, $path);
            }
        }

        return $paths;
    }

    private function unknownTokenErrors(array $input, array $allowed, string $prefix, array &$errors): void
    {
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (! array_key_exists($key, $allowed)) {
                $errors[$path] = 'This theme token is not supported by the approved theme contract.';
                continue;
            }
            if (is_array($value) && is_array($allowed[$key])) {
                $this->unknownTokenErrors($value, $allowed[$key], $path, $errors);
            }
        }
    }
}
