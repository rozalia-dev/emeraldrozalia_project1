<?php

namespace App\Policies;

use App\Models\{Order, User};

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->hasAnyPermission(['online.sales.view', 'reports.view']);
    }

    public function view(User $user, Order $order): bool
    {
        return ($user->is_admin
                || (int) $order->user_id === (int) $user->id
                || $user->hasAnyPermission(['online.sales.view', 'reports.view']))
            && $this->sameCompany($order);
    }

    public function update(User $user, Order $order): bool
    {
        return ($user->is_admin || $user->hasAnyPermission(['online.sales.edit', 'reports.edit']))
            && $this->sameCompany($order);
    }

    public function invoice(User $user, Order $order): bool
    {
        return ($user->is_admin
                || (int) $order->user_id === (int) $user->id
                || $user->hasAnyPermission(['online.sales.view', 'reports.view']))
            && $this->sameCompany($order);
    }

    private function sameCompany(Order $order): bool
    {
        $companyId = session('company_id');

        return $companyId === null || (int) $order->company_id === (int) $companyId;
    }
}
