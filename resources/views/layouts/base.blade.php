<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        // Merge the English strings under the active locale so a key missing in a translation never shows raw.
        $i18n = [];
        foreach (array_merge(['common'], isset($area) ? [$area] : []) as $group) {
            $i18n[$group] = array_replace_recursive((array) trans($group, [], config('app.fallback_locale')), (array) trans($group));
        }
    @endphp
    <script>
        window.i18n = @json($i18n);
    </script>
</head>
<body>
    @yield('body')
</body>
</html>
