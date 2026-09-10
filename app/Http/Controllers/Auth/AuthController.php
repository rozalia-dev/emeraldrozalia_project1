<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $candidate = User::where('email', $credentials['email'])->first();
        if ($candidate && (($candidate->status ?? 'active') !== 'active' || $candidate->locked_at)) {
            $this->authAudit('authentication.blocked-login', $candidate, $request, ['reason'=>$candidate->locked_at ? 'locked' : 'inactive']);
            return back()->withErrors(['email' => 'This account is currently unavailable. Contact an administrator.'])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            $user->forceFill(['last_login_at'=>now()])->save();
            $this->authAudit('authentication.login', $user, $request, ['status'=>'success']);

            return redirect()->intended(
                $user->is_admin ? route('admin.dashboard') : route('account.dashboard')
            );
        }

        $this->authAudit('authentication.failed-login', $candidate, $request, ['status'=>'failed']);
        return back()
            ->withErrors(['email' => 'Those credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data + ['status'=>'active']);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $this->authAudit('users.registered', $user, $request, ['status'=>'active']);

        return redirect()
            ->route('account.dashboard')
            ->with('success', 'Account created. Please verify your email to unlock every account feature.');
    }

    public function forgotForm()
    {
        return view('auth.forgot');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'success',
            'If an account exists for that email, a password reset link has been sent.'
        );
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function authAudit(string $action, ?User $user, Request $request, array $after): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) return;
        AuditLog::create([
            'user_id'=>$user?->id,
            'action'=>$action,
            'subject_type'=>$user ? User::class : null,
            'subject_id'=>$user?->id,
            'request_id'=>(string)Str::uuid(),
            'ip_address'=>$request->ip(),
            'after'=>$after,
        ]);
    }
}
