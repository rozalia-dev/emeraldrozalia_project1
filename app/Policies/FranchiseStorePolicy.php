<?php

namespace App\Policies;

use App\Models\FranchiseStore;
use App\Models\User;

class FranchiseStorePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, FranchiseStore $store): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $store);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, FranchiseStore $store): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $store);
    }

    public function transition(User $user, FranchiseStore $store): bool
    {
        return $this->update($user, $store);
    }

    public function delete(User $user, FranchiseStore $store): bool
    {
        return $this->allowed($user, 'delete') && $this->sameCompany($user, $store);
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

    private function sameCompany(User $user, FranchiseStore $store): bool
    {
        $companyId = session('company_id');

        return $user->is_admin
            || $companyId === null
            || ($store->company_id !== null && (int) $store->company_id === (int) $companyId);
    }
}
