<?php

namespace App\Services;

use App\Models\{Company, ThemeVersion, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CpanelThemeVersionService
{
    public const ENVIRONMENTS = ['production', 'staging', 'development'];

    public const DEFAULT_TOKENS = [
        'colors' => [
            'sidebar_start' => '#052617',
            'sidebar_end' => '#02170e',
            'sidebar_text' => '#ffffff',
            'topbar' => '#063020',
            'topbar_text' => '#ffffff',
            'accent' => '#7fbd42',
            'background' => '#f3f5f1',
            'surface' => '#ffffff',
            'text' => '#15221b',
            'muted' => '#647168',
            'border' => '#d8dfda',
            'focus' => '#7fbd42',
            'success' => '#16784a',
            'warning' => '#b7791f',
            'danger' => '#b42318',
            'info' => '#2676cc',
        ],
        'typography' => [
            'font_family' => 'Inter, Arial, sans-serif',
            'heading_family' => 'Inter, Arial, sans-serif',
            'base_size' => '14px',
        ],
        'spacing' => [
            'radius' => '8px',
            'sidebar_width' => '245px',
        ],
        'controls' => [
            'button_radius' => '6px',
            'input_radius' => '6px',
        ],
    ];

    private const COLOR_PATHS = [
        'colors.sidebar_start', 'colors.sidebar_end', 'colors.sidebar_text',
        'colors.topbar', 'colors.topbar_text', 'colors.accent', 'colors.background',
        'colors.surface', 'colors.text', 'colors.muted', 'colors.border', 'colors.focus',
        'colors.success', 'colors.warning', 'colors.danger', 'colors.info',
    ];

    private const DIMENSION_PATHS = [
        'typography.base_size', 'spacing.radius', 'spacing.sidebar_width',
        'controls.button_radius', 'controls.input_radius',
    ];

    private const FONT_PATHS = ['typography.font_family', 'typography.heading_family'];

    public function defaults(): array
    {
        return self::DEFAULT_TOKENS;
    }

    public function currentCompany(): ?Company
    {
        return app(TenantContext::class)->company();
    }

    public function currentLocale(): string
    {
        return (string) (app()->getLocale() ?: 'en');
    }

    public function currentEnvironment(?string $environment = null): string
    {
        $environment = (string) ($environment ?: config('app.theme_environment', 'production'));

        return in_array($environment, self::ENVIRONMENTS, true) ? $environment : 'production';
    }

    /** @return Builder<ThemeVersion> */
    public function query(?Company $company = null, string $environment = 'production', string $locale = 'en'): Builder
    {
        return ThemeVersion::query()
            ->withoutGlobalScopes()
            ->where('scope', 'admin')
            ->where('environment', $environment)
            ->where('locale', $locale)
            ->when(
                $company,
                fn (Builder $query): Builder => $query->where('company_id', $company->getKey()),
                fn (Builder $query): Builder => $query->whereNull('company_id'),
            );
    }

    public function versions(?Company $company = null, ?string $environment = null, ?string $locale = null)
    {
        $company ??= $this->currentCompany();
        $environment = $this->currentEnvironment($environment);
        $locale ??= $this->currentLocale();

        return $this->query($company, $environment, $locale)->latest('version')->get();
    }

    public function activeSnapshot(?Company $company = null, string $environment = 'production', ?string $locale = null): array
    {
        $company ??= $this->currentCompany();
        $locale ??= $this->currentLocale();
        $environment = $this->currentEnvironment($environment);
        $theme = $this->query($company, $environment, $locale)
            ->where('status', ThemeVersion::STATUS_ACTIVE)
            ->latest('version')
            ->first();

        if (! $theme) {
            return [
                'meta' => [
                    'source' => 'default-cpanel-theme-fallback',
                    'uuid' => null,
                    'version' => 0,
                    'status' => 'fallback',
                    'environment' => $environment,
                    'locale' => $locale,
                ],
                'tokens' => self::DEFAULT_TOKENS,
            ];
        }

        return [
            'meta' => [
                'source' => 'active-cpanel-theme-version',
                'uuid' => $theme->uuid,
                'version' => (int) $theme->version,
                'status' => $theme->status,
                'environment' => $theme->environment,
                'locale' => $theme->locale,
                'activated_at' => $theme->activated_at?->toIso8601String(),
            ],
            'tokens' => $this->mergeTokens($theme->token_payload ?? []),
        ];
    }

    public function createDraft(array $attributes, ?User $user = null): ThemeVersion
    {
        $company = $attributes['company'] ?? $this->currentCompany();
        $environment = $this->currentEnvironment($attributes['environment'] ?? null);
        $locale = (string) ($attributes['locale'] ?? $this->currentLocale());
        $tokens = $this->validatedTokens($attributes['tokens'] ?? $this->activeSnapshot($company, $environment, $locale)['tokens']);
        $userId = $user?->getKey() ?? auth()->id();

        return DB::transaction(function () use ($attributes, $company, $environment, $locale, $tokens, $userId): ThemeVersion {
            $version = ((int) $this->query($company, $environment, $locale)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version')) + 1;

            $theme = ThemeVersion::create([
                'company_id' => $company?->getKey(),
                'name' => trim((string) ($attributes['name'] ?? 'Emerald Rozalia cPanel Theme')),
                'scope' => 'admin',
                'environment' => $environment,
                'locale' => $locale,
                'version' => $version,
                'status' => ThemeVersion::STATUS_DRAFT,
                'token_payload' => $tokens,
                'asset_references' => [],
                'created_by' => $userId,
                'notes' => $attributes['notes'] ?? null,
            ]);

            AuditTrail::record('settings.cpanel-theme.draft_created', $theme, null, $this->auditState($theme));

            return $theme;
        });
    }

    public function updateDraft(ThemeVersion $theme, array $attributes, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only draft cPanel themes can be edited.');
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

        AuditTrail::record('settings.cpanel-theme.draft_updated', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function validateTheme(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only draft cPanel themes can be validated.');
        $errors = $this->tokenErrors($theme->token_payload ?? []);
        $before = $this->auditState($theme);

        if ($errors !== []) {
            $theme->update(['validation_errors' => $errors, 'validated_at' => null, 'validated_by' => null]);
            AuditTrail::record('settings.cpanel-theme.validation_failed', $theme, $before, $this->auditState($theme->fresh()));
            throw ValidationException::withMessages($errors);
        }

        $theme->update([
            'validation_errors' => null,
            'validated_at' => now(),
            'validated_by' => $user?->getKey() ?? auth()->id(),
        ]);
        AuditTrail::record('settings.cpanel-theme.validated', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function submitForApproval(ThemeVersion $theme): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_DRAFT, 409, 'Only a draft cPanel theme can be submitted.');
        abort_unless($theme->validated_at, 409, 'Validate the cPanel theme before requesting approval.');

        return $this->transition($theme, ThemeVersion::STATUS_PENDING_APPROVAL, 'settings.cpanel-theme.submitted');
    }

    public function approve(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_PENDING_APPROVAL, 409, 'Only pending cPanel themes can be approved.');
        $before = $this->auditState($theme);
        $theme->update([
            'status' => ThemeVersion::STATUS_APPROVED,
            'approved_by' => $user?->getKey() ?? auth()->id(),
            'approved_at' => now(),
        ]);
        AuditTrail::record('settings.cpanel-theme.approved', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function activate(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless($theme->status === ThemeVersion::STATUS_APPROVED, 409, 'Approve the cPanel theme before activation.');

        return DB::transaction(function () use ($theme, $user): ThemeVersion {
            $current = $this->query($theme->company, $theme->environment, $theme->locale)
                ->where('status', ThemeVersion::STATUS_ACTIVE)
                ->whereKeyNot($theme->getKey())
                ->lockForUpdate()
                ->first();

            if ($current) {
                $beforeCurrent = $this->auditState($current);
                $current->update(['status' => ThemeVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('settings.cpanel-theme.superseded', $current, $beforeCurrent, $this->auditState($current->fresh()));
            }

            $before = $this->auditState($theme);
            $theme->update([
                'status' => ThemeVersion::STATUS_ACTIVE,
                'activated_by' => $user?->getKey() ?? auth()->id(),
                'activated_at' => now(),
            ]);
            AuditTrail::record('settings.cpanel-theme.activated', $theme, $before, $this->auditState($theme->fresh()));

            return $theme->fresh();
        });
    }

    public function disable(ThemeVersion $theme, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($theme);
        abort_unless(in_array($theme->status, [ThemeVersion::STATUS_ACTIVE, ThemeVersion::STATUS_APPROVED], true), 409, 'This cPanel theme cannot be disabled from its current state.');
        $before = $this->auditState($theme);
        $theme->update([
            'status' => ThemeVersion::STATUS_DISABLED,
            'disabled_by' => $user?->getKey() ?? auth()->id(),
            'disabled_at' => now(),
        ]);
        AuditTrail::record('settings.cpanel-theme.disabled', $theme, $before, $this->auditState($theme->fresh()));

        return $theme->fresh();
    }

    public function rollback(ThemeVersion $target, ?User $user = null): ThemeVersion
    {
        $this->assertCurrentContext($target);
        abort_unless(! in_array($target->status, [ThemeVersion::STATUS_DRAFT, ThemeVersion::STATUS_DISABLED], true), 409, 'Only an approved, active or superseded cPanel theme can be rolled back.');

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
                abort_unless($target, 409, 'There is no prior cPanel theme version available for rollback.');
            }

            $rollbackBefore = $current ? $this->auditState($current) : null;
            if ($current) {
                $current->update(['status' => ThemeVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('settings.cpanel-theme.superseded', $current, $rollbackBefore, $this->auditState($current->fresh()));
            }

            $version = ((int) $this->query($target->company, $target->environment, $target->locale)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version')) + 1;
            $userId = $user?->getKey() ?? auth()->id();
            $rollback = ThemeVersion::create([
                'company_id' => $target->company_id,
                'name' => 'Rollback: '.$target->name,
                'scope' => 'admin',
                'environment' => $target->environment,
                'locale' => $target->locale,
                'version' => $version,
                'status' => ThemeVersion::STATUS_ACTIVE,
                'token_payload' => $this->mergeTokens($target->token_payload ?? []),
                'asset_references' => [],
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
                'notes' => 'Created from a prior approved cPanel theme version.',
            ]);
            AuditTrail::record('settings.cpanel-theme.rolled_back', $rollback, $rollbackBefore, $this->auditState($rollback));

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

        abort_unless($theme->scope === 'admin' && (int) $theme->company_id === (int) $companyId, 404);
    }

    private function mergeTokens(array $tokens): array
    {
        return array_replace_recursive(self::DEFAULT_TOKENS, $tokens);
    }

    private function validatedTokens(array $tokens): array
    {
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
        $this->unknownTokenErrors($tokens, self::DEFAULT_TOKENS, '', $errors);
        $merged = $this->mergeTokens($tokens);

        foreach (self::COLOR_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! is_string($value) || ! preg_match('/\A#[0-9a-fA-F]{6}\z/', $value)) {
                $errors[$path] = 'Use a six-digit hexadecimal colour such as #075b2f.';
            }
        }
        foreach (self::DIMENSION_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! is_string($value) || ! preg_match('/\A\d{1,4}px\z/', $value)) {
                $errors[$path] = 'Use a pixel value such as 14px.';
            }
        }
        foreach (self::FONT_PATHS as $path) {
            $value = data_get($merged, $path);
            if (! in_array($value, ['Inter, Arial, sans-serif', 'system-ui, sans-serif', 'Georgia, serif'], true)) {
                $errors[$path] = 'Choose one of the approved font stacks.';
            }
        }

        $sidebarWidth = (int) rtrim((string) data_get($merged, 'spacing.sidebar_width'), 'px');
        if ($sidebarWidth < 190 || $sidebarWidth > 360) {
            $errors['spacing.sidebar_width'] = 'Sidebar width must be between 190px and 360px.';
        }

        $baseSize = (int) rtrim((string) data_get($merged, 'typography.base_size'), 'px');
        if ($baseSize < 11 || $baseSize > 20) {
            $errors['typography.base_size'] = 'Base font size must be between 11px and 20px.';
        }

        return $errors;
    }

    private function unknownTokenErrors(array $input, array $allowed, string $prefix, array &$errors): void
    {
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (! array_key_exists($key, $allowed)) {
                $errors[$path] = 'This cPanel theme token is not supported.';
                continue;
            }
            if (is_array($value) && is_array($allowed[$key])) {
                $this->unknownTokenErrors($value, $allowed[$key], $path, $errors);
            }
        }
    }
}
