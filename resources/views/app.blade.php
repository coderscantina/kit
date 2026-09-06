<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f0f1f4">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#16171b">
    {{-- Home-screen install. Chromium and Safari read the manifest; iOS still
         wants its own tags for the title, the icon and a status bar that lets
         the shell paint under it (see `pt-safe` in app.css). --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @if (file_exists(public_path('build/manifest.webmanifest')))
        <link rel="manifest" href="/build/manifest.webmanifest">
    @endif
    <meta name="app-version" content="{{ config('app.version') }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    {{-- @json swallows its trailing newline, so the statement ends with an explicit semicolon. --}}
    <script>window.__APP_CONFIG__ = @json($config);</script>
    {{-- Applies the stored colour mode and accent before first paint. Runs here,
         ahead of the bundle, because waiting for Vue to mount means a white flash
         on every load. The storage keys are shared with
         resources/js/composables/useColorMode.ts and useAccentColor.ts. --}}
    <script>
        (function () {
            try {
                var mode = localStorage.getItem('kit:color-mode') || 'system';
                var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

                var accent = localStorage.getItem('kit:accent');
                if (accent && accent !== 'blue' && /^[a-z]+$/.test(accent)) {
                    document.documentElement.setAttribute('data-accent', accent);
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/js/main.ts'])
</head>
<body class="h-full bg-surface text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
