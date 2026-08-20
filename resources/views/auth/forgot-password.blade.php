<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Forgot password — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Forgot your password?</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @if (session('status') === 'reset-link-sent')
        <p>If an account exists for that email, a reset link has been sent.</p>
    @endif

    <form method="POST" action="{{ url()->current() }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <button type="submit">Email password reset link</button>
    </form>
</body>
</html>
