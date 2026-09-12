<?php

namespace App\Policies;

use App\Models\{Banner,User};

class BannerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->is_admin;
    }
}
