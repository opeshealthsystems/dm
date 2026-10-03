# Handover: continue the build

Read this first. It is the single source of truth for where the project stands.

## Goal

Convert a legacy hand-written PHP multi-vendor marketplace into a clean **Laravel modular
monolith** that is **API-first** and sells **API-as-a-product** (OAuth 2.0, OpenAPI docs,
API keys, webhooks, usage metering). Then build buyer / vendor / admin dashboards as
clients of that API. It is a general, **lawful** marketplace for any legal goods. One
deployment = many vendors, one owner.

Hard requirement from the owner: **payments and escrow must never break.** Port them
only behind tests that pin the existing behaviour.

## Open blockers (need the owner)

1. **GitHub push is blocked on authentication.** The credential manager waits for an
   interactive login on the owner's PC, so pushes from an unattended session hang. Fix:
   sign in once (`git push` from a terminal, or GitHub Desktop); then pushes work.
   Local commits are safe; nothing is lost.
2. **MySQL was stopped** (Laragon closed). Tests use in-memory SQLite and pass; start
   Laragon, then run `php artisan migrate` and `php artisan db:seed` against a real DB.
3. **Real-chain verification is not done.** Bitcoin/Monero code is tested with faked HTTP.
   Before real money: run on Bitcoin testnet and a Monero stagenet wallet-rpc, and run the
   test suite on MySQL (row locks cannot be proven on SQLite).
4. **Legacy payment source is not in this repo** (see "Legacy reference"). Only needed if
   behaviour has to be re-compared with the old app.
5. **Legacy data import** is not written; decide whether old users/orders must carry over.

## Security notes

- Scope checks are role-aware (`RequireScopes`, `RequireAnyScope`): a browser session token
  carries every scope, so the user's role must also allow the scope (`User::allowedScopes`).
  Covered by `AdminUiTest`. Always use `scopes:` / `scope:` in route groups.
- `SecurityHeaders` sets CSP/frame/nosniff/HSTS. CSP still allows `unsafe-inline` and
  `unsafe-eval` (Alpine + inline page scripts). Backlog: per-request nonces + Alpine CSP build.
- The test suite takes ~3.5 minutes, mostly pure-PHP Bitcoin key derivation.

## Current state (all tests green: 195 tests, 1675 assertions)

| Area | State |
|---|---|
| Laravel 13 app, PHP 8.3, MySQL | done |
| OAuth 2.0 (Passport) with role-based scopes | done |
| OpenAPI docs (Scramble) at `/docs/api`, spec in `docs/openapi.json` | done |
| Rate limits (`api` 120/min per user/IP, `auth` 10/min per IP + 5/min per email) | done |
| Identity: register, login, me, logout | done |
| Catalog: public list/show, vendor create/update/delete (policy-protected) | done |
| Orders: cart, checkout (split per vendor, stock locking, price snapshot), ship, confirm, cancel | done |
| Order state machine `OrderLifecycle` (idempotent `markPaid`, escrow held/released/refunded) | done |
| Domain events (`OrderPlaced/Paid/Shipped/Completed/Cancelled/Refunded`) | done, no listeners yet |
| Reputation: reviews (completed-order buyers only, one per product), vendor reply, helpful votes, follow, cached rating aggregates | done, 11 tests |
| Messaging: order/general conversations, encrypted bodies, read receipts, blocks, notifications, Order-event listeners | done, 10 tests |
| Developer Platform: hashed API keys (`dm_live_...`), per-day usage metering, HMAC-signed webhooks with retry | done, 15 tests |
| Payments: `PaymentGateway` contract, Bitcoin (xpub derivation, BlockCypher), Monero (wallet-rpc), `payments:poll` scheduler, only caller of `markPaid` | done, tested with faked HTTP only |
| Wallet (append-only ledger, commission), payout requests + admin approve/reject/paid | done, 28 tests with Escrow |
| Escrow: disputes (open/message/admin resolve -> refund or release), vendor fees + tiers | done |
| Admin API: `admin:create`, users, catalog moderation, categories, orders, settings, stats, audit log | done, 23 tests |
| Web UI: storefront, buyer area, seller area, admin panel (Blade + Alpine + Tailwind 4, calls `/api/v1` only) | done, QA'd in a browser at 360px/1280px, light/dark, LTR/RTL |
| i18n: 12 languages (en nl de fr es it pt ru zh-hans ja ko ar) for common/buyer/seller/admin; RTL for Arabic | done, parity test enforces keys + placeholders |
| Role-aware scope middleware, security headers | done |
| Community (forum), public content/SEO pages (about, FAQ, terms), sitemap/robots | **not started** |
| Import of legacy users/orders into the new schema | **not started** |
| Password reset, email verification, 2FA (TOTP) | **not started** |

## Architecture rules

- Modules live in `app/Modules/<Module>/{Http,Models,Actions,Policies,Events,Exceptions}`.
  Controllers are thin; business rules live in `Actions/*` services.
- Modules never reach into each other's tables. They talk through public services and
  **domain events** (see `app/Modules/Orders/Events`).
- Order status, escrow and shipment fields change **only** in
  `App\Modules\Orders\Actions\OrderLifecycle`. Models keep `$fillable = []` for these.
- Money is integer **cents** + 3-letter currency. Crypto amounts will be integer
  smallest units (sats, piconero), never floats.
- Every state change takes a row lock (`lockForUpdate`) and re-checks state, so webhook
  and request replays are safe.
- All routes are under `/api/v1`, grouped in `routes/api.php` by required OAuth scope.
- Roles: `buyer`, `vendor`, `admin`. Scopes per role: `User::allowedScopes()`.
  Admin accounts can never be created through the API.
- Business-rule failures throw `OrderException` (renders JSON 409/422).
- Authorization uses Policies (`ProductPolicy`, `OrderPolicy`); register new ones in
  `AppServiceProvider::boot()`.
- Every endpoint gets a docblock; Scramble turns it into the OpenAPI docs. Regenerate:
  `php artisan scramble:export --path=docs/openapi.json`.

## Gotchas

- `phpunit.xml` pins `APP_URL=http://localhost`. If `APP_URL` has a path prefix
  (e.g. `/laravel/public`), tests build URLs wrongly and every route returns 404.
- Tests that need Passport tokens create a personal client in `setUp()`
  (`passport:client --personal`). `Passport::actingAs()` needs no client.
- Tests run on in-memory SQLite. They cannot prove true concurrency. **Add a MySQL
  test run (CI service container) before payments go live.**
- New Eloquent models returned straight after `save()` do not contain DB defaults; call
  `->refresh()` before serializing (this caused a real bug in checkout).
- Dev server: `php artisan serve`. Behind Apache the app was served at a sub-path with a
  `.htaccess` override in `public/` that disables inherited month-long caching.
- The framework skeleton once shipped agent instructions telling tools to install
  "Laravel Boost". They were removed; do not install it unless the owner asks.

## Legacy reference (NOT in this repo, by design)

The old PHP app exists only on the owner's machine (`C:\xampp\htdocs\Dutchmarket`). It was
deliberately not published because it contains credentials, user data and removed code.
Porting payments needs these legacy files; the owner must provide them privately:

- `services/PaymentService.php`, `MoneroService.php`, `MoneroViewOnlyWallet.php`
- `helpers/BitcoinHelper.php`, `MoneroHelper.php`, `PaymentLogger.php`
- `models/Transaction.php`, `MoneroPaymentDetector.php`, `MoneroPayoutRequest.php`, `Payout.php`, `PlatformWallet.php`, `Dispute.php`, `VendorFee.php`
- `controllers/TransactionController.php`, `WalletController.php`, `MoneroPayoutController.php`, `DisputeController.php`, `FeeController.php`
- `cron/monero_payment_cron.php`, `cron/payout-verify.php`, `includes/JobRunner.php`, `BlockCypherHelper.php`

Legacy order flow (already ported): `product_orders.escrow_status`
pending -> held -> released | refunded; `shipment_status` pending -> shipped -> delivered.

## Backlog, in order

1. **Go-live gates (must pass before real money):** run the suite on MySQL (CI service
   container); Bitcoin testnet + Monero stagenet end-to-end (order -> pay -> detect ->
   escrow -> ship -> confirm -> vendor credit -> payout); independent security review;
   configure real xpub / wallet-rpc via `.env` (never commit); queue worker + scheduler
   (`php artisan schedule:work`) running; HTTPS.
2. **Account safety:** password reset, email verification, TOTP 2FA (admins first),
   login throttling by account, session hardening.
3. **Hardening:** CSP nonces + Alpine CSP build (drop `unsafe-inline/eval`), tighten
   CORS (`config/cors.php` currently allows all origins for `api/*`), backups, log
   shipping, rate limits per API key plan.
4. **Community** (forum) and public content pages (about, FAQ, terms, privacy,
   how-it-works), sitemap/robots, SEO meta per page, all in the 12 languages.
5. **Developer Platform follow-ups:** rate plans per key, sandbox/test mode, usage
   dashboard UI, API docs landing page with an OAuth quickstart.
6. **Legacy import:** artisan command mapping legacy `users`, `products`, `product_orders`
   into the new schema (idempotent, dry-run flag) - only if the owner wants history.
7. **Known UI/API gaps from QA:** dispute list shows `Order #id` (add `order_number` to
   `DisputeResource`); payout currency should be a select; seller wallet makes 8 API
   calls (add one summary endpoint); sales-30d card sums first 100 ledger rows (add a
   server aggregate); review edit/delete; dedicated `messages:*` scope; product form
   category select. `buyer.order.pay_unavailable` English text changed - other locales
   still carry the old wording (re-translate).
8. **CI:** GitHub Actions running `composer install`, `npm run build`, `php artisan test`
   on PHP 8.3 + MySQL.

## Decisions already made

- One marketplace per install, many vendors. Payments: BTC and XMR kept.
- Vendors cannot place orders by default (no `orders:write` scope); revisit if wanted.
- Laravel tables are a clean new schema; legacy tables are not reused directly.
- OAuth 2.0 for third parties (authorization code + PKCE, client credentials), plus a
  first-party login endpoint that issues a role-limited token.

## Commands

```bash
php artisan test                                   # run all tests
php artisan route:list --path=api/v1
php artisan scramble:export --path=docs/openapi.json
php artisan migrate:fresh                          # dev only
```

## Monero gateway (done)
- `MoneroGateway` (wallet-rpc `create_address`, piconero math, `get_transfers`), config `config/monero.php`, `Gateways/Monero/{MoneroRpc,MoneroRate,MoneroHealth}`. Registered in PaymentsServiceProvider. 12 tests in `tests/Feature/Payments/MoneroGatewayTest.php`.
- Legacy view-only derivation (`MoneroHelper`) and the daemon mempool scanner used keccak stubs, not ed25519; they were NOT ported. Wallet RPC is the only source.
- Deviations: confirmations = least-confirmed tx (legacy: max); pool txs counted as detected; no `/api/v1/health/monero` route yet (no admin scope exists; use `MoneroHealth::check()`).
