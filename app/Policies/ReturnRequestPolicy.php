<?php

namespace App\Policies;

use App\Models\{ReturnRequest, User};

class ReturnRequestPolicy
{
    public function view(User $user, ReturnRequest $return): bool
    {
        return $this->owns($user, $return);
    }

    public function create(User $user): bool
    {
        return true;
    }

    private function owns(User $user, ReturnRequest $return): bool
    {
        $companyId = session('company_id');

        return (int) $return->user_id === (int) $user->id
            && ($companyId === null || (int) $return->company_id === (int) $companyId);
    }
}
