<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('locales.supported.' . app()->getLocale() . '.dir', 'ltr') }}">
<body>
<p>{{ __('security.mail.reset_line', ['app' => config('app.name')]) }}</p>
<p><a href="{{ $url }}">{{ __('security.mail.reset_button') }}</a></p>
<p>{{ __('security.mail.reset_expires', ['minutes' => $minutes]) }}</p>
<p>{{ $url }}</p>
<p>{{ __('security.mail.ignore') }}</p>
</body>
</html>
