<?php

namespace App\Services;

use App\Models\ThemeVersion;
use App\Models\User;

final class ThemeQuickApplyService
{
    public function __construct(private readonly ThemeVersionService $themes)
    {
    }

    public function apply(array $attributes, ?User $user = null): ThemeVersion
    {
        $theme = $this->themes->createDraft($attributes, $user);
        $theme = $this->themes->validateTheme($theme, $user);
        $theme = $this->themes->submitForApproval($theme);
        $theme = $this->themes->approve($theme, $user);

        return $this->themes->activate($theme, $user);
    }

    public function resetToDefault(string $environment = 'production', string $locale = 'en', ?User $user = null): ThemeVersion
    {
        return $this->apply([
            'name' => 'Emerald Rozalia Default',
            'environment' => $environment,
            'locale' => $locale,
            'tokens' => $this->themes->defaults(),
            'notes' => 'Reset to the Emerald Rozalia baseline theme.',
        ], $user);
    }
}
