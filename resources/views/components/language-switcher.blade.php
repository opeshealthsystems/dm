@props(['id' => 'lang-select'])
@php($current = app()->getLocale())
<label class="sr-only" for="{{ $id }}">{{ __('common.language') }}</label>
<select id="{{ $id }}" class="lang-select !w-auto text-sm" onchange="location.href='{{ url('/locale') }}/'+this.value">
    @foreach (config('locales.supported') as $code => $meta)
        <option value="{{ $code }}" @selected($code === $current)>{{ $meta['name'] }}</option>
    @endforeach
</select>
