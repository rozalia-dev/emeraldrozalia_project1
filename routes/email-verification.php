<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $user = User::query()->findOrFail($id);

    abort_unless(
        hash_equals((string) $hash, sha1($user->getEmailForVerification())),
        403,
        'Invalid verification link.'
    );

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()
        ->route($user->is_admin ? 'admin.dashboard' : 'account.dashboard')
        ->with('success', 'Email verified successfully.');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
