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
        return $user->hasPermission('website.products.view');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->hasPermission('website.products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('website.products.create');
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->hasPermission('website.products.edit');
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->hasPermission('website.products.delete');
    }
}
