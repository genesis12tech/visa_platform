<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Verify your email — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Check your email</h1>

    <p>We've sent a verification link to your email address. Click it to activate your account.</p>

    @if (session('status') === 'verification-link-sent')
        <p>A new verification link has been sent.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Resend verification email</button>
    </form>
</body>
</html>
