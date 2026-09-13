<?php

namespace App\Services;

use App\Models\AdminRecord;

final class AdminActionRegistry
{
    /**
     * Return the server-backed actions available for a generic cPanel row.
     * Dedicated domains can extend this contract without changing the table UI.
     */
    public function for(AdminRecord $record): array
    {
        $module = (string) $record->module;
        $id = $record->getKey();

        $actions = [
            [
                'key' => 'view',
                'label' => 'View & edit',
                'type' => 'link',
                'href' => route('admin.resource.show', [$module, $id]),
                'tone' => 'primary',
            ],
            [
                'key' => 'duplicate',
                'label' => 'Duplicate',
                'type' => 'form',
                'method' => 'POST',
                'href' => route('admin.resource.duplicate', [$module, $id]),
                'tone' => 'default',
            ],
        ];

        if ($record->trashed()) {
            $actions[] = [
                'key' => 'restore',
                'label' => 'Restore',
                'type' => 'form',
                'method' => 'POST',
                'href' => route('admin.resource.restore', [$module, $id]),
                'tone' => 'positive',
            ];
            $actions[] = [
                'key' => 'permanently-delete',
                'label' => 'Permanently delete',
                'type' => 'form',
                'method' => 'DELETE',
                'href' => route('admin.resource.permanent-destroy', [$module, $id]),
                'tone' => 'danger',
                'confirm' => 'Permanently delete this record? This cannot be undone.',
            ];
        } else {
            if ($record->status !== 'archived') {
                $actions[] = [
                    'key' => 'archive',
                    'label' => 'Archive',
                    'type' => 'form',
                    'method' => 'POST',
                    'href' => route('admin.resource.archive', [$module, $id]),
                    'tone' => 'default',
                    'confirm' => 'Archive this record?',
                ];
            }
            $actions[] = [
                'key' => 'trash',
                'label' => 'Move to trash',
                'type' => 'form',
                'method' => 'POST',
                'href' => route('admin.resource.trash', [$module, $id]),
                'tone' => 'danger',
                'confirm' => 'Move this record to trash? You can restore it later.',
            ];
        }

        return $actions;
    }
}
