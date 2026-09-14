<?php

namespace App\Http\Requests\Concerns;

trait AuthorizesCommunication
{
    protected function communicationAuthorized(string $action = 'view'): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

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
}
