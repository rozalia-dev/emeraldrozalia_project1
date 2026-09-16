<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserBulkActionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in([
                'activate',
                'deactivate',
                'lock',
                'unlock',
                'enable_2fa',
                'disable_2fa',
            ])],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        if (in_array($action, ['deactivate', 'lock'], true) && in_array((int) $request->user()->id, $ids, true)) {
            throw ValidationException::withMessages([
                'ids' => 'Your signed-in account cannot be deactivated or locked through a bulk action.',
            ]);
        }

        DB::transaction(function () use ($ids, $action): void {
            $users = User::query()->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($users->count() === count($ids), 422, 'One or more selected users are unavailable.');

            foreach ($users as $user) {
                $before = $user->only(['status', 'locked_at', 'two_factor_enabled']);

                match ($action) {
                    'activate' => $user->update(['status' => 'active']),
                    'deactivate' => $user->update(['status' => 'inactive']),
                    'lock' => $user->update(['locked_at' => now()]),
                    'unlock' => $user->update(['locked_at' => null]),
                    'enable_2fa' => $user->update(['two_factor_enabled' => true]),
                    'disable_2fa' => $user->update(['two_factor_enabled' => false]),
                };

                AuditTrail::record(
                    'users.bulk_'.$action,
                    $user,
                    $before,
                    $user->fresh()->only(['status', 'locked_at', 'two_factor_enabled'])
                );
            }
        });

        return back()->with('success', 'Selected user access settings updated.');
    }
}
