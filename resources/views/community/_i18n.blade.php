{{-- Community strings for the page's JavaScript (the base layout only ships common + area), plus two small helpers. --}}
@php
    $communityStrings = array_replace_recursive((array) trans('community', [], config('app.fallback_locale')), (array) trans('community'));
@endphp
<script>
    window.i18n = window.i18n || {};
    window.i18n.community = @json($communityStrings);
    /** Translate a stored lang key, else use the admin-typed fallback text. */
    window.cTr = (key, fallback) => { if (!key) return fallback; const v = t(key); return v === key ? fallback : v; };
    window.cName = (c) => cTr(c.name_key, c.name);
    window.cDesc = (c) => cTr(c.description_key, c.description);
    window.cHandle = (a) => (a && (a.handle || ('#' + a.id))) || '';
</script>
