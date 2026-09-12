<?php

namespace App\Policies;

use App\Models\{Product,User};

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return $product->is_active && in_array($product->status, ['active', 'published'], true);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('website.products.edit') && $this->sameCompany($user, $product);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('website.products.create');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('website.products.delete') && $this->sameCompany($user, $product);
    }

    private function sameCompany(User $user, Product $product): bool
    {
        return ! session('company_id') || (int) $product->company_id === (int) session('company_id');
    }
}
