<?php

namespace App\Policies;

use App\Models\FranchiseMilestone;
use App\Models\User;

class FranchiseMilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, FranchiseMilestone $milestone): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $milestone);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, FranchiseMilestone $milestone): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $milestone);
    }

    public function transition(User $user, FranchiseMilestone $milestone): bool
    {
        return $this->update($user, $milestone);
    }

    public function delete(User $user, FranchiseMilestone $milestone): bool
    {
        return $this->allowed($user, 'delete') && $this->sameCompany($user, $milestone);
    }

    private function allowed(User $user, string $action): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $permissions = [
            'franchise.retail.stores.'.$action,
            'franchise.retail.stores.'.($action === 'edit' ? 'update' : $action),
            'franchise.'.$action,
            'franchise.management.'.($action === 'edit' ? 'edit' : $action),
        ];

        return $user->hasAnyPermission(array_values(array_unique($permissions)));
    }

    private function sameCompany(User $user, FranchiseMilestone $milestone): bool
    {
        $companyId = session('company_id');

        return $user->is_admin
            || $companyId === null
            || ($milestone->company_id !== null && (int) $milestone->company_id === (int) $companyId);
    }
}
