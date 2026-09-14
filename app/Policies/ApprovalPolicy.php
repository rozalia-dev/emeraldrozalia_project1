<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;
use App\Services\TenantContext;

class ApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, Approval $approval): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $approval);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, Approval $approval): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $approval);
    }

    public function delete(User $user, Approval $approval): bool
    {
        return $this->allowed($user, 'delete') && $this->sameCompany($user, $approval);
    }

    public function decide(User $user, Approval $approval): bool
    {
        return $this->allowed($user, 'approve') && $this->sameCompany($user, $approval);
    }

    private function allowed(User $user, string $action): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $permissions = [];
        foreach (['communication.center', 'communication', 'communications'] as $prefix) {
            $permissions[] = $prefix.'.'.$action;
            if ($action === 'edit') {
                $permissions[] = $prefix.'.update';
            }
            if ($action === 'approve') {
                $permissions[] = $prefix.'.edit';
                $permissions[] = $prefix.'.update';
            }
        }

        return $user->hasAnyPermission(array_values(array_unique($permissions)));
    }

    private function sameCompany(User $user, Approval $approval): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $companyId = app(TenantContext::class)->company($user)?->id;

        return $companyId !== null
            && $approval->company_id !== null
            && (int) $approval->company_id === (int) $companyId;
    }
}
