@extends('layouts.admin')

@section('title', 'Admin Profile')

@push('styles')
<link rel="stylesheet" href="/css/admin-profile.css?v=20260916-v1">
@endpush

@section('content')
<div class="admin-profile-page">
    <div class="admin-profile-page-head">
        <div>
            <p class="admin-profile-eyebrow">ADMIN ACCOUNT</p>
            <h1>My Profile</h1>
            <p>Manage your cPanel administrator identity and security settings. This account area is separate from the customer account portal.</p>
        </div>
        <a class="admin-profile-back" href="{{ route('admin.dashboard') }}"><x-icon name="arrow-left" size="15" /> Back to Dashboard</a>
    </div>

    <div class="admin-profile-layout">
        <aside class="admin-profile-summary">
            <div class="admin-profile-large-avatar"><x-icon name="user" size="34" /></div>
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <span class="admin-profile-role">Super Admin</span>

            <dl>
                <div><dt>Status</dt><dd>{{ str($user->status ?? 'active')->headline() }}</dd></div>
                <div><dt>Department</dt><dd>{{ $user->department ?: 'Not set' }}</dd></div>
                <div><dt>Employee Code</dt><dd>{{ $user->employee_code ?: 'Not set' }}</dd></div>
                <div><dt>Last Login</dt><dd>{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd></div>
                <div><dt>Password Changed</dt><dd>{{ $user->password_changed_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd></div>
            </dl>
        </aside>

        <section class="admin-profile-content">
            <div class="admin-profile-card">
                <div class="admin-profile-card-head">
                    <div>
                        <h2>Profile Information</h2>
                        <p>Update the administrator details shown across the cPanel.</p>
                    </div>
                    <x-icon name="user" size="20" />
                </div>

                <form method="post" action="{{ route('admin.profile.update') }}" class="admin-profile-form">
                    @csrf
                    @method('PATCH')

                    <label>
                        <span>Full Name</span>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
                    </label>

                    <label>
                        <span>Email Address</span>
                        <input type="email" value="{{ $user->email }}" readonly aria-describedby="admin-email-note">
                        <small id="admin-email-note">Administrator email is managed through Users &amp; Roles to avoid accidental account ownership changes.</small>
                    </label>

                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="50" autocomplete="tel">
                    </label>

                    <label>
                        <span>Department</span>
                        <input type="text" name="department" value="{{ old('department', $user->department) }}" maxlength="120">
                    </label>

                    <div class="admin-profile-form-actions">
                        <button type="submit" class="admin-profile-primary"><x-icon name="check" size="15" /> Save Profile</button>
                    </div>
                </form>
            </div>

            <div class="admin-profile-card">
                <div class="admin-profile-card-head">
                    <div>
                        <h2>Security</h2>
                        <p>Change the password for this administrator account.</p>
                    </div>
                    <x-icon name="lock" size="20" />
                </div>

                <form method="post" action="{{ route('admin.profile.password') }}" class="admin-profile-form">
                    @csrf
                    @method('PATCH')

                    <label>
                        <span>Current Password</span>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </label>

                    <label>
                        <span>New Password</span>
                        <input type="password" name="password" required minlength="12" autocomplete="new-password">
                        <small>Use at least 12 characters.</small>
                    </label>

                    <label>
                        <span>Confirm New Password</span>
                        <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
                    </label>

                    <div class="admin-profile-form-actions">
                        <button type="submit" class="admin-profile-primary"><x-icon name="lock" size="15" /> Update Password</button>
                    </div>
                </form>
            </div>

            <div class="admin-profile-card admin-profile-access-card">
                <div class="admin-profile-card-head">
                    <div>
                        <h2>Access Context</h2>
                        <p>Your assigned cPanel roles and companies.</p>
                    </div>
                    <x-icon name="shield" size="20" />
                </div>

                <div class="admin-profile-access-grid">
                    <div>
                        <h3>Roles</h3>
                        @forelse($user->roles as $role)
                            <span>{{ $role->name }}</span>
                        @empty
                            <span>Super Admin</span>
                        @endforelse
                    </div>
                    <div>
                        <h3>Companies</h3>
                        @forelse($user->companies as $company)
                            <span>{{ $company->name }}</span>
                        @empty
                            <span>Emerald Rozalia Limited</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
