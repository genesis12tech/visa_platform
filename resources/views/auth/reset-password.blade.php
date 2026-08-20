<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Reset password — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Reset your password</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ url()->current() }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required autofocus>

        <label for="password">New password</label>
        <input id="password" name="password" type="password" required>

        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>

        <button type="submit">Reset password</button>
    </form>
</body>
</html>
