<script>
/* Small helpers shared by the seller screens. Defined once per page load. */
window.sellerUi = window.sellerUi || {
    err(e) {
        const all = e && e.errors ? Object.values(e.errors).flat() : [];
        if (all.length) return all.join(' ');
        return (e && e.message && e.message !== 'Error') ? e.message : t('common.error');
    },
    fieldErrors(e) {
        const out = {};
        Object.entries((e && e.errors) || {}).forEach(([k, v]) => { out[k] = Array.isArray(v) ? v.join(' ') : String(v); });
        return out;
    },
    /* "12.5" / "12,50" -> 1250, or null when it is not a clean amount with at most 2 decimals. No floats. */
    toCents(v) {
        const s = String(v ?? '').trim().replace(',', '.');
        if (!/^\d{1,9}(\.\d{1,2})?$/.test(s)) return null;
        const parts = s.split('.');
        return parseInt(parts[0], 10) * 100 + parseInt(((parts[1] || '') + '00').slice(0, 2), 10);
    },
    fromCents(c) {
        const n = Math.abs(parseInt(c, 10) || 0);
        return (c < 0 ? '-' : '') + Math.floor(n / 100) + '.' + String(n % 100).padStart(2, '0');
    },
    badge(kind, value) {
        const map = {
            order: { pending_payment: 'badge-warn', paid: 'badge-accent', shipped: 'badge-accent', completed: 'badge-ok', cancelled: '', disputed: 'badge-bad' },
            payout: { pending: 'badge-warn', approved: 'badge-accent', paid: 'badge-ok', rejected: 'badge-bad' },
            product: { draft: '', active: 'badge-ok', archived: 'badge-warn' },
            dispute: { open: 'badge-bad', resolved: 'badge-ok' },
            delivery: { delivered: 'badge-ok', failed: 'badge-bad', pending: 'badge-warn' },
        };
        return 'badge ' + ((map[kind] || {})[value] || '');
    },
    /* Fill the id into a URL generated with id 0. */
    url(tpl, id) { return tpl.replace(/\/0(\/|$)/, '/' + id + '$1'); },
    ymd(daysAgo) { const d = new Date(Date.now() - daysAgo * 86400000); return d.toISOString().slice(0, 10); },
};
</script>
