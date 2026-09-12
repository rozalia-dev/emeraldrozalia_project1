<?php

namespace App\Policies;

use App\Models\CommunicationTemplate;
use App\Models\User;

class CommunicationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, CommunicationTemplate $template): bool
    {
        return $user->is_admin && $this->sameCompany($template);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, CommunicationTemplate $template): bool
    {
        return $user->is_admin && $this->sameCompany($template);
    }

    public function delete(User $user, CommunicationTemplate $template): bool
    {
        return $user->is_admin && $this->sameCompany($template);
    }

    private function sameCompany(CommunicationTemplate $template): bool
    {
        return ! session('company_id') || (int) $template->company_id === (int) session('company_id');
    }
}
