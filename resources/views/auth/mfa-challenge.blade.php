<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Verify your identity — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Enter your verification code</h1>

    <p>Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('staff.mfa.challenge.store') }}">
        @csrf

        <label for="code">Code</label>
        <input id="code" name="code" type="text" required autofocus>

        <button type="submit">Verify</button>
    </form>
</body>
</html>
