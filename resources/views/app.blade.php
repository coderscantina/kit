<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-version" content="{{ config('app.version') }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    {{-- @json swallows its trailing newline, so the statement ends with an explicit semicolon. --}}
    <script>window.__APP_CONFIG__ = @json($config);</script>
    @vite(['resources/js/main.ts'])
</head>
<body class="h-full bg-surface text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
