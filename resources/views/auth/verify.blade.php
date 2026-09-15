@extends('layouts.site')
@section('title','Verify Email — Emerald Rozalia')
@section('content')
<section class="auth-page">
    <div class="auth-card">
        <h1>Verify Your Email</h1>
        <p>We use email verification to protect your customer account.</p>
        <p>A secure verification link is sent to <strong>{{ auth()->user()->email }}</strong>. Open that link to activate your account.</p>

        @if(session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if(session('warning'))
            <p role="alert">{{ session('warning') }}</p>
        @endif
        @if($errors->any())
            <p role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="post" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn" type="submit">RESEND VERIFICATION EMAIL</button>
        </form>
        <p>Please also check your spam or junk folder. Verification links expire automatically for security.</p>

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">LOG OUT</button>
        </form>
    </div>
</section>
@endsection
