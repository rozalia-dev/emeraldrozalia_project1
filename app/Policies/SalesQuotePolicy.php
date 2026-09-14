<?php

namespace App\Policies;

use App\Models\SalesQuote;
use App\Models\User;

class SalesQuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin
            || $user->hasAnyPermission([
                'online.sales.view',
                'reports.view',
                'applications.leads.view',
                'franchise.management.view',
            ]);
    }

    public function view(User $user, SalesQuote $quote): bool
    {
        return $this->viewAny($user) && $this->sameCompany($user, $quote);
    }

    public function update(User $user, SalesQuote $quote): bool
    {
        return $this->canEdit($user) && $this->sameCompany($user, $quote);
    }

    public function approve(User $user, SalesQuote $quote): bool
    {
        return $this->canEdit($user) && $this->sameCompany($user, $quote);
    }

    public function reject(User $user, SalesQuote $quote): bool
    {
        return $this->canEdit($user) && $this->sameCompany($user, $quote);
    }

    public function cancel(User $user, SalesQuote $quote): bool
    {
        return $this->canEdit($user) && $this->sameCompany($user, $quote);
    }

    public function convert(User $user, SalesQuote $quote): bool
    {
        return $this->canEdit($user) && $this->sameCompany($user, $quote);
    }

    private function canEdit(User $user): bool
    {
        return $user->is_admin
            || $user->hasAnyPermission([
                'online.sales.edit',
                'reports.edit',
                'applications.leads.edit',
                'franchise.management.edit',
            ]);
    }

    private function sameCompany(User $user, SalesQuote $quote): bool
    {
        $companyId = session('company_id');
        if (! $companyId) {
            return true;
        }
        if ((int) $quote->company_id === (int) $companyId) {
            return true;
        }

        return $user->is_admin && $quote->company_id === null;
    }
}
