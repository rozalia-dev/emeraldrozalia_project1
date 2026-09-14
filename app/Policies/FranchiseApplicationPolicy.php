<?php

namespace App\Policies;

use App\Models\FranchiseApplication;
use App\Models\User;

class FranchiseApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, FranchiseApplication $application): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $application);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, FranchiseApplication $application): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $application);
    }

    public function transition(User $user, FranchiseApplication $application): bool
    {
        return $this->update($user, $application);
    }

    public function approve(User $user, FranchiseApplication $application): bool
    {
        return $this->sameCompany($user, $application)
            && ($user->is_admin || $user->hasAnyPermission([
                'applications.leads.edit',
                'applications.leads.update',
                'applications.leads.approve',
                'franchise.approve',
                'franchise.update',
            ]));
    }

    public function delete(User $user, FranchiseApplication $application): bool
    {
        return $this->allowed($user, 'delete') && $this->sameCompany($user, $application);
    }

    private function allowed(User $user, string $action): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $permissions = [
            'applications.leads.'.$action,
            'applications.leads.'.($action === 'edit' ? 'update' : $action),
            'franchise.'.$action,
            'franchise.management.'.($action === 'edit' ? 'edit' : $action),
        ];

        return $user->hasAnyPermission(array_values(array_unique($permissions)));
    }

    private function sameCompany(User $user, FranchiseApplication $application): bool
    {
        $companyId = session('company_id');

        return $user->is_admin
            || $companyId === null
            || ($application->company_id !== null && (int) $application->company_id === (int) $companyId);
    }
}
