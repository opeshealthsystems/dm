import Alpine from 'alpinejs';

/*
 * Shared front-end helpers. The dashboards are thin clients of /api/v1:
 * the browser session carries a Passport cookie, so no tokens are handled here.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const locale = document.documentElement.lang || 'en';

/** Call the JSON API. Throws {status, message, errors} on failure. */
async function api(path, { method = 'GET', body = null, query = null } = {}) {
    const url = new URL('/api/v1/' + path.replace(/^\/+/, ''), window.location.origin);
    if (query) Object.entries(query).forEach(([k, v]) => v !== null && v !== '' && url.searchParams.set(k, v));

    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept-Language': locale,
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : null,
    });

    if (res.status === 204) return null;
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) window.location.href = '/login';
    if (!res.ok) throw { status: res.status, message: data.message ?? 'Error', errors: data.errors ?? {} };

    return data;
}

/** Translate a key from the strings the server put on the page (window.i18n). */
function t(key, vars = {}) {
    const text = key.split('.').reduce((o, k) => (o ? o[k] : undefined), window.i18n ?? {}) ?? key;
    return Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(':' + k, v), text);
}

const money = (cents, currency = 'EUR') =>
    new Intl.NumberFormat(locale, { style: 'currency', currency }).format((cents ?? 0) / 100);

const date = (iso) =>
    iso ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '';

/** First validation message of an API error, else its message, else a generic text. */
const errText = (e) => Object.values(e?.errors ?? {})[0]?.[0] ?? e?.message ?? t('common.error');

/** Format an integer amount of satoshi (bitcoin) or piconero (monero) without float maths. */
function fmtCrypto(atomic, method) {
    const decimals = method === 'monero' ? 12 : 8;
    const s = String(atomic ?? 0).replace(/\D/g, '').padStart(decimals + 1, '0');
    const whole = s.slice(0, -decimals);
    const frac = s.slice(-decimals).replace(/0+$/, '');

    return frac ? `${whole}.${frac}` : whole;
}

/** Badge class for an order status. */
const statusClass = (s) =>
    ({ completed: 'badge-ok', pending_payment: 'badge-warn', paid: '', shipped: '', disputed: 'badge-bad' })[s] ?? '';

window.api = api;
window.statusClass = statusClass;
window.errText = errText;
window.fmtCrypto = fmtCrypto;
window.t = t;
window.money = money;
window.fmtDate = date;

window.Alpine = Alpine;
Alpine.start();

/*
 * Stacked-table labels: copy each header cell's text onto the matching body cell so the
 * phone layout (see app.css) can show "Price: 19.99" per row. Runs for Alpine-rendered rows too.
 */
function labelTables(root = document) {
    root.querySelectorAll('.table-wrap table').forEach((table) => {
        const heads = [...table.querySelectorAll('thead th')].map((h) => h.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((tr) => {
            [...tr.children].forEach((td, i) => {
                if (td.tagName === 'TD' && td.dataset.label === undefined) td.dataset.label = heads[i] ?? '';
            });
        });
    });
}
new MutationObserver(() => labelTables()).observe(document.documentElement, { childList: true, subtree: true });
document.addEventListener('DOMContentLoaded', () => labelTables());
