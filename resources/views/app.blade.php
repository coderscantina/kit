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
    {{-- Applies the stored colour mode before first paint. Runs here, ahead of the
         bundle, because waiting for Vue to mount means a white flash on every load.
         The storage key is shared with resources/js/composables/useColorMode.ts. --}}
    <script>
        (function () {
            try {
                var mode = localStorage.getItem('kit:color-mode') || 'system';
                var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    @vite(['resources/js/main.ts'])
</head>
<body class="h-full bg-surface text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
