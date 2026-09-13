<?php

namespace App\Policies;

use App\Models\AdminRecord;
use App\Models\User;

class AdminRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, AdminRecord $record): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, AdminRecord $record): bool
    {
        return $user->is_admin;
    }

    public function archive(User $user, AdminRecord $record): bool
    {
        return $user->is_admin && ! $record->trashed();
    }

    public function trash(User $user, AdminRecord $record): bool
    {
        return $user->is_admin && ! $record->trashed();
    }

    public function restore(User $user, AdminRecord $record): bool
    {
        return $user->is_admin && $record->trashed();
    }

    public function permanentlyDelete(User $user, AdminRecord $record): bool
    {
        return $user->is_admin && $record->trashed();
    }
}
