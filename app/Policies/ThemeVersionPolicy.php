<?php

namespace App\Policies;

use App\Models\ThemeVersion;
use App\Models\User;

class ThemeVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $theme);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'update') && $this->sameCompany($user, $theme);
    }

    public function validate(User $user, ThemeVersion $theme): bool
    {
        return $this->update($user, $theme);
    }

    public function submit(User $user, ThemeVersion $theme): bool
    {
        return $this->update($user, $theme);
    }

    public function approve(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($user, $theme);
    }

    public function activate(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($user, $theme);
    }

    public function disable(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'update') && $this->sameCompany($user, $theme);
    }

    public function rollback(User $user, ThemeVersion $theme): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($user, $theme);
    }

    private function allowed(User $user, string $action): bool
    {
        return $user->is_admin || $user->hasPermission('settings.'.$action);
    }

    private function sameCompany(User $user, ThemeVersion $theme): bool
    {
        $companyId = session('company_id');

        return $companyId === null || (int) $theme->company_id === (int) $companyId;
    }
}
