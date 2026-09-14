<?php

namespace App\Policies;

use App\Models\{PaymentTransaction, User};

class PaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->hasAnyPermission(['online.sales.view', 'reports.view']);
    }

    public function view(User $user, PaymentTransaction $payment): bool
    {
        return $this->canAccess($user, $payment);
    }

    public function update(User $user, PaymentTransaction $payment): bool
    {
        return ($user->is_admin || $user->hasAnyPermission(['online.sales.edit', 'reports.edit']))
            && $this->canAccess($user, $payment);
    }

    private function canAccess(User $user, PaymentTransaction $payment): bool
    {
        $companyId = session('company_id');

        return $companyId === null || (int) $payment->company_id === (int) $companyId;
    }
}
