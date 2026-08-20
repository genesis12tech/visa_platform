<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Set up two-factor authentication — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Set up two-factor authentication</h1>

    <p>Scan this QR code with your authenticator app, or enter the key manually.</p>

    <div>{!! $qrCodeInline !!}</div>

    <p>Manual entry key: <code>{{ $secret }}</code></p>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('staff.mfa.enroll.store') }}">
        @csrf

        <label for="code">Enter the 6-digit code from your app</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus>

        <button type="submit">Confirm</button>
    </form>
</body>
</html>
