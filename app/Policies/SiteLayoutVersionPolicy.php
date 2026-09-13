<?php

namespace App\Policies;

use App\Models\{SiteLayoutVersion, User};

class SiteLayoutVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($layout);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'update') && $this->sameCompany($layout);
    }

    public function validate(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->update($user, $layout);
    }

    public function submit(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->update($user, $layout);
    }

    public function approve(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($layout);
    }

    public function activate(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($layout);
    }

    public function disable(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'update') && $this->sameCompany($layout);
    }

    public function rollback(User $user, SiteLayoutVersion $layout): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($layout);
    }

    private function allowed(User $user, string $action): bool
    {
        return $user->is_admin || $user->hasPermission('pages.'.$action);
    }

    private function sameCompany(SiteLayoutVersion $layout): bool
    {
        $companyId = session('company_id');

        return $companyId === null || (int) $layout->company_id === (int) $companyId;
    }
}
