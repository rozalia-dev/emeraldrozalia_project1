<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AdminAppearanceService
{
    public const DEFAULT_THEME = 'guide-dark';
    public const THEMES = [
        'guide-dark' => 'Guide Dark',
        'light' => 'Light',
    ];

    public function current(?Company $company = null): string
    {
        $company ??= app(TenantContext::class)->company();
        $theme = (string) data_get($company?->settings, 'cpanel.theme', self::DEFAULT_THEME);

        return array_key_exists($theme, self::THEMES) ? $theme : self::DEFAULT_THEME;
    }

    public function options(): array
    {
        return self::THEMES;
    }

    public function update(string $theme, ?Company $company = null, ?User $user = null): string
    {
        if (! array_key_exists($theme, self::THEMES)) {
            throw ValidationException::withMessages(['cpanel_theme' => 'Choose a supported cPanel theme.']);
        }

        $company ??= app(TenantContext::class)->company();
        abort_unless($company, 409, 'Select a company before changing the cPanel theme.');

        $before = $this->current($company);
        $settings = (array) ($company->settings ?? []);
        data_set($settings, 'cpanel.theme', $theme);
        $company->update(['settings' => $settings]);

        AuditTrail::record(
            'settings.cpanel_theme.updated',
            $company,
            ['theme' => $before],
            ['theme' => $theme, 'actor_id' => $user?->getKey() ?? auth()->id()],
        );

        return $theme;
    }
}
