<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Services\TenantContext;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $conversation);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $conversation);
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

    private function sameCompany(User $user, Conversation $conversation): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $companyId = app(TenantContext::class)->company($user)?->id;

        return $companyId !== null
            && $conversation->company_id !== null
            && (int) $conversation->company_id === (int) $companyId;
    }
}
