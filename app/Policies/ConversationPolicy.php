<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->is_admin && $this->sameCompany($conversation);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->is_admin && $this->sameCompany($conversation);
    }

    private function sameCompany(Conversation $conversation): bool
    {
        return ! session('company_id') || (int) $conversation->company_id === (int) session('company_id');
    }
}
