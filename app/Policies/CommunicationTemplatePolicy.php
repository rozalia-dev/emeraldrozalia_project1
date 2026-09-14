<?php

namespace App\Policies;

use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\TenantContext;

class CommunicationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, CommunicationTemplate $template): bool
    {
        return $this->allowed($user, 'view') && $this->sameCompany($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, CommunicationTemplate $template): bool
    {
        return $this->allowed($user, 'edit') && $this->sameCompany($user, $template);
    }

    public function delete(User $user, CommunicationTemplate $template): bool
    {
        return $this->allowed($user, 'delete') && $this->sameCompany($user, $template);
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

    private function sameCompany(User $user, CommunicationTemplate $template): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $companyId = app(TenantContext::class)->company($user)?->id;

        return $companyId !== null
            && $template->company_id !== null
            && (int) $template->company_id === (int) $companyId;
    }
}
