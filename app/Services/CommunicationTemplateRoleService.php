<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunicationTemplateRoleService
{
    public const CANONICAL_ROLES = [
        'super-admin' => 'Super Admin / Administrator',
        'marketing-manager' => 'Marketing Manager',
        'store-manager' => 'Store Manager',
        'api-user' => 'API User / Developer',
        'operations-manager' => 'Operations Manager',
        'finance-manager' => 'Finance Manager / Accountant',
        'franchise-manager' => 'Franchise Manager',
        'sales-representative' => 'Sales Representative',
        'support-manager' => 'Support Manager / Communication Support',
    ];

    private const ROLE_ALIASES = [
        'administrator' => 'super-admin',
        'admin' => 'super-admin',
        'system-administrator' => 'super-admin',
        'marketing' => 'marketing-manager',
        'store' => 'store-manager',
        'developer' => 'api-user',
        'api-developer' => 'api-user',
        'operations' => 'operations-manager',
        'accountant' => 'finance-manager',
        'finance' => 'finance-manager',
        'franchise' => 'franchise-manager',
        'sales' => 'sales-representative',
        'support' => 'support-manager',
        'communication-support' => 'support-manager',
    ];

    public function canUse(?User $user, CommunicationTemplate $template): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $allowed = $this->allowedRoleKeys($template);
        if ($allowed === []) {
            // Backwards compatibility: legacy templates without role metadata
            // remain available to users who already have Communication Center access.
            return true;
        }

        return array_intersect($allowed, $this->userRoleKeys($user)) !== [];
    }

    public function authorizeUse(?User $user, CommunicationTemplate $template): void
    {
        if (! $this->canUse($user, $template)) {
            abort(403, 'This email template is not available to your assigned role.');
        }
    }

    public function visibleTemplateIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $templates = CommunicationTemplate::query()->get(['id', 'variables']);

        if ($this->isSuperAdmin($user)) {
            return $templates->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $templates
            ->filter(fn (CommunicationTemplate $template): bool => $this->canUse($user, $template))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function applySelection(array $payload, array $roleKeys): array
    {
        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $normalized = collect($roleKeys)
            ->map(fn ($role) => $this->canonicalKey((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            unset($variables['_roles']);
        } else {
            $variables['_roles'] = $normalized;
        }

        ksort($variables);
        $payload['variables'] = $variables;

        return $payload;
    }

    public function allowedRoleKeys(CommunicationTemplate $template): array
    {
        $variables = is_array($template->variables) ? $template->variables : [];
        $roles = is_array($variables['_roles'] ?? null) ? $variables['_roles'] : [];

        return collect($roles)
            ->map(fn ($role) => $this->canonicalKey((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function userRoleKeys(User $user): array
    {
        if ($user->is_admin) {
            return ['super-admin'];
        }

        return $user->roles()
            ->where('roles.is_active', true)
            ->get()
            ->filter(function (Role $role): bool {
                $pivot = $role->pivot;
                if (($pivot?->status ?? 'active') !== 'active') {
                    return false;
                }

                $expiresAt = $pivot?->expires_at;

                return blank($expiresAt) || now()->lt($expiresAt);
            })
            ->map(fn (Role $role) => $this->canonicalKey((string) $role->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isSuperAdmin(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return in_array('super-admin', $this->userRoleKeys($user), true);
    }

    public function roleOptions(): array
    {
        $options = self::CANONICAL_ROLES;

        Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->each(function ($name) use (&$options): void {
                $key = $this->canonicalKey((string) $name);
                if ($key !== '' && ! array_key_exists($key, $options)) {
                    $options[$key] = (string) $name;
                }
            });

        return $options;
    }

    public function safeBrowserData(CommunicationTemplate $template): array
    {
        $variables = is_array($template->variables) ? $template->variables : [];
        $attachment = is_array($variables['_attachment'] ?? null) ? $variables['_attachment'] : [];

        return [
            'uuid' => $template->uuid,
            'name' => $template->name,
            'subject' => $template->subject,
            'body' => $template->body,
            'status' => $template->status,
            'category' => (string) ($variables['category'] ?? ''),
            'language' => (string) ($variables['language'] ?? 'en'),
            'roles' => $this->allowedRoleKeys($template),
            'attachment_mode' => (string) ($attachment['mode'] ?? 'none'),
            'attachment_label' => (string) ($attachment['label'] ?? ''),
            'attachment_url' => (string) ($attachment['url'] ?? ''),
            'attachment_file_name' => (string) ($attachment['file_name'] ?? ''),
            'has_attachment_file' => filled($attachment['file_path'] ?? null),
            'attachment_preference' => (string) ($variables['_attachment_preference'] ?? 'none'),
        ];
    }

    public function canonicalKey(string $value): string
    {
        $key = Str::slug($value);

        return self::ROLE_ALIASES[$key] ?? $key;
    }
}
