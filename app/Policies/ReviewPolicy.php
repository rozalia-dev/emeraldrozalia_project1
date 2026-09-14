<?php

namespace App\Policies;

use App\Models\{Review, User};

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin
            || $user->hasAnyPermission(['reviews.view', 'website.reviews.view', 'reports.view']);
    }

    public function view(User $user, Review $review): bool
    {
        return $this->viewAny($user) && $this->sameCompany($review);
    }

    public function create(User $user): bool
    {
        return $user->is_admin
            || $user->hasAnyPermission(['reviews.create', 'reviews.edit', 'website.reviews.edit']);
    }

    public function update(User $user, Review $review): bool
    {
        return ($user->is_admin
                || $user->hasAnyPermission(['reviews.edit', 'website.reviews.edit']))
            && $this->sameCompany($review);
    }

    private function sameCompany(Review $review): bool
    {
        $companyId = session('company_id');

        return $companyId === null || (int) $review->company_id === (int) $companyId;
    }
}
