<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Your recovery codes — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Save your recovery codes</h1>

    <p>Store these somewhere safe. Each code can be used once if you lose access to your authenticator app. They will not be shown again.</p>

    <ul>
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ul>

    <a href="/">Continue</a>
</body>
</html>
