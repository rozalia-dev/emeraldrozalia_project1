<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user->load(['roles', 'companies']);

        return view('admin.profile.show', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:120'],
        ]);

        $before = $user->only(['name', 'phone', 'department']);
        $user->update($data);

        AuditTrail::record(
            'admin.profile_updated',
            $user,
            $before,
            $user->fresh()->only(['name', 'phone', 'department'])
        );

        return back()->with('success', 'Admin profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ])->save();

        AuditTrail::record('admin.password_changed', $user, null, [
            'password_changed_at' => optional($user->password_changed_at)->toIso8601String(),
        ]);

        return back()->with('success', 'Admin password updated.');
    }
}
