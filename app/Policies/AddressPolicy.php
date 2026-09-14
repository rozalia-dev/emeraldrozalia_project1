<?php

namespace App\Policies;

use App\Models\{Address, User};

class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $this->owns($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->owns($user, $address);
    }

    private function owns(User $user, Address $address): bool
    {
        $companyId = session('company_id');

        return (int) $address->user_id === (int) $user->id
            && ($companyId === null || (int) $address->company_id === (int) $companyId);
    }
}
