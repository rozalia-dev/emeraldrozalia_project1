@extends('layouts.site')

@section('title', 'Login / Register — Emerald Rozalia')

@section('content')
    <section class="auth-hero">
        <span class="eyebrow">CUSTOMER ACCOUNT</span>
        <h1>Welcome Back</h1>
        <p>Login to your account or create a new one to enjoy a premium Irish experience.</p>
    </section>

    <section class="auth-page auth-login" aria-labelledby="account-access-title">
        <h2 id="account-access-title" class="sr-only">Account access</h2>

        <article class="auth-card auth-panel">
            <div class="auth-heading">
                <span class="auth-icon" aria-hidden="true">◯</span>
                <div>
                    <h2>Login</h2>
                    <p>Welcome back. Please login to your account.</p>
                </div>
            </div>

            <form method="post" action="{{ route('login.submit') }}">
                @csrf
                <label for="login-email">Email Address</label>
                <input id="login-email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email address" autocomplete="email" required>
                <label for="login-password">Password</label>
                <input id="login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <div class="auth-form-row">
                    <label class="checkbox-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> <span>Remember me</span></label>
                    <a href="{{ route('password.request') }}">Forgot Password?</a>
                </div>
                <button class="btn auth-submit" type="submit">LOGIN <span aria-hidden="true">→</span></button>
            </form>

            <div class="auth-trust">
                <strong>Your data is safe with us.</strong>
                <span>We never share your information.</span>
            </div>
        </article>

        <article class="auth-card auth-panel">
            <div class="auth-heading">
                <span class="auth-icon" aria-hidden="true">◯+</span>
                <div>
                    <h2>Register</h2>
                    <p>Create an account and enjoy these benefits.</p>
                </div>
            </div>

            <ul class="auth-benefit-list">
                <li><strong>Faster Checkout</strong><span>Save your details for a quicker checkout.</span></li>
                <li><strong>Track Orders</strong><span>Easily track and manage your orders.</span></li>
                <li><strong>Exclusive Rewards</strong><span>Get access to special offers and new arrivals.</span></li>
            </ul>

            <form method="post" action="{{ route('register') }}">
                @csrf
                <label for="register-name">Full Name</label>
                <input id="register-name" name="name" value="{{ old('name') }}" placeholder="Enter your full name" autocomplete="name" required>
                <label for="register-email">Email Address</label>
                <input id="register-email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email address" autocomplete="email" required>
                <label for="register-phone">Phone <span>(optional)</span></label>
                <input id="register-phone" name="phone" value="{{ old('phone') }}" placeholder="Your phone number" autocomplete="tel">
                <label for="register-password">Password</label>
                <input id="register-password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                <label for="register-password-confirmation">Confirm Password</label>
                <input id="register-password-confirmation" type="password" name="password_confirmation" placeholder="Confirm your password" autocomplete="new-password" required>
                <label class="checkbox-label"><input type="checkbox" required> <span>I agree to the Terms &amp; Conditions and Privacy Policy.</span></label>
                <button class="btn auth-submit" type="submit">REGISTER <span aria-hidden="true">→</span></button>
            </form>
        </article>
    </section>

    <section class="auth-benefits-strip" aria-label="Emerald Rozalia benefits">
        <div><strong>Irish Made in Limerick</strong><span>Designed, cut and sewn in our Limerick factory.</span></div>
        <div><strong>Premium Quality</strong><span>Finest fabrics and expert craftsmanship.</span></div>
        <div><strong>Fast Worldwide Delivery</strong><span>Reliable shipping wherever you are.</span></div>
        <div><strong>Secure &amp; Trusted</strong><span>Your privacy and security are our priority.</span></div>
    </section>
@endsection
