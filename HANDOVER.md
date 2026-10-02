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

## Current state (all tests green: 26 tests, 119 assertions)

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
| Payments (BTC/XMR), Escrow release/payout, Wallet, Disputes, Fees | **not started** |
| Messaging, Reputation (ratings/reviews), Community (forum), Content/SEO/i18n, Admin | **not started** (empty module folders) |
| Developer Platform (API keys, webhooks, usage) | **not started** |
| Web UI: buyer / vendor / admin dashboards | **not started** |
| Import of legacy users/orders into the new schema | **not started** |

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

1. **Payments + Escrow port** (highest risk). Write characterization tests first,
   `PaymentGateway` / `AddressDeriver` interfaces for BTC and XMR, a scheduler job
   replacing `cron/monero_payment_cron.php`, and a listener that calls
   `OrderLifecycle::markPaid` on confirmed payment. Release/refund funds on
   `OrderCompleted` / `OrderRefunded`. Keep amounts as integers; log every money event.
2. **Wallet, vendor fees, payouts, disputes** (admin resolves -> `OrderLifecycle::refund`
   or release).
3. **Developer Platform:** per-vendor API keys with scopes + rate plans, usage metering
   table, HMAC-signed webhooks with retries (listen to Order events), sandbox/test mode.
4. **Reputation** (ratings/reviews only for completed orders), **Messaging**
   (order-linked threads + notifications), **Community** (forum), **Content/SEO/i18n**
   (port 12 language files unchanged into Laravel `lang/`).
5. **Admin module + API:** users (suspend/verify vendor), catalog moderation, orders,
   disputes, settings, audit log.
6. **UI:** buyer dashboard, vendor dashboard, admin panel, storefront. Use the API only.
   Design system first: one token set (colors, spacing, type) with a distinct brand
   identity; no clashing colors, no generic template look; verify light/dark contrast.
   Check every API route has a screen and every screen has working routes (no dead links).
7. **Legacy import:** artisan command mapping legacy `users`, `products`, `product_orders`
   into the new schema (idempotent, dry-run flag).
8. **Hardening:** MySQL CI, secrets only in `.env`, tighten CORS (`config/cors.php`
   currently allows all origins), audit log, 2FA (TOTP), password reset, e-mail verify.

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
