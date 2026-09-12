<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Approval $approval): bool
    {
        return $user->is_admin && $this->sameCompany($approval);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Approval $approval): bool
    {
        return $user->is_admin && $this->sameCompany($approval);
    }

    public function delete(User $user, Approval $approval): bool
    {
        return $user->is_admin && $this->sameCompany($approval);
    }

    public function decide(User $user, Approval $approval): bool
    {
        return $user->is_admin && $this->sameCompany($approval);
    }

    private function sameCompany(Approval $approval): bool
    {
        return ! session('company_id') || (int) $approval->company_id === (int) session('company_id');
    }
}
