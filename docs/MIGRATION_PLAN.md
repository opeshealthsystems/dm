# Laravel Migration Plan: API-First, API-as-a-Product, Modular Monolith

Goal: turn the current hand-rolled PHP marketplace into a plug-and-play Laravel
platform that any owner can deploy to run a **legal** online shop, with a
documented, versioned public API as the primary interface.

## Ground rules

1. **Payments never break.** No payment/escrow code is rewritten from memory. It is
   first pinned by characterization tests against the legacy behaviour, then ported
   line-for-line, then verified by running both side by side before cutover.
2. **Strangler approach.** The legacy app keeps serving traffic. Laravel is added next
   to it, shares the same database, and takes over route groups one at a time.
3. **Lawful commerce only.** The platform is a general multi-vendor marketplace for legal
   goods. Legacy pages, categories, SEO and tooling that served any other purpose are
   removed and are never ported.
4. **One database, migrations from day one.** Existing tables are captured as a
   baseline migration; every later change is a Laravel migration.

## Target architecture (modular monolith)

Single Laravel app, code grouped by module under `app/Modules/*`, each with its own
routes, controllers (thin), actions/services, models, policies, tests. Modules talk
through public service interfaces and events, never through each other's tables.

| Module | Replaces (legacy) |
|---|---|
| Identity | User, UserProfile, PasswordReset, TOTP, 2FA, session/auth |
| Catalog | Product, ProductVariation, VariationGroup, Category, search |
| Orders | Cart, Transaction (order side), BulkOrder, ShippingMethod |
| Payments | PaymentService, MoneroService, BitcoinHelper, MoneroHelper, PaymentLogger, cron jobs |
| Escrow & Disputes | escrow flow in Transaction, Dispute, Payout, VendorFee |
| Wallet | WalletController, PlatformWallet, deposits, payout requests |
| Messaging | Message, conversations, notifications, tickets |
| Reputation | Rating, Review, Follow |
| Community | Forum* |
| Content & SEO | pages, content_translations, SEO, sitemap, i18n (12 languages) |
| Admin | AdminController, settings, broadcasts, audit log |
| Developer Platform | NEW: API keys, scopes, usage, webhooks, docs |

## API as a product

- `/api/v1/...` JSON API is the only way the web UI and third parties touch data.
  The web front end becomes a client of the same API.
- Auth: Laravel Sanctum (session for the web UI, personal access tokens for
  integrations) plus per-shop API keys with scopes (`catalog:read`, `orders:write`...).
- Versioning (`v1`), consistent error format (RFC 7807), pagination, idempotency keys
  on every money-moving endpoint.
- Rate limiting per key and plan; usage metering table; abuse protection.
- Webhooks (order paid, escrow released, dispute opened...) signed with HMAC and retried.
- OpenAPI spec generated from code (Scramble) and published as a developer portal
  with sandbox keys and test-mode payments.
- Plug-and-play: web installer, `.env` wizard, theme/branding settings, seed data for a
  blank shop (no products, one admin), one-command deploy.

## Phases

**Phase 0: Safety net (before any port)**
- Snapshot code + non-listing data (owner decision: dumps/zips/`Dutchmarket_old` stay frozen).
- Install Composer deps, scaffold Laravel in `laravel/` next to the legacy app,
  same DB (legacy database), PHPUnit/Pest + a test database.
- Baseline migration from the live schema. Resolve the 3 existing FK constraints.
- Characterization tests for the payment path: create order, address derivation,
  payment detection, escrow release/refund, payout, vendor fee, dispute resolution.
  Record fixtures from `logs/payments.log` and the `payment_transactions`/`monero_*` tables.

**Phase 1: Platform skeleton**
- Module layout, base API controller, error format, auth (Sanctum), roles/policies,
  rate limiting, API key model, request logging, OpenAPI generation.
- Identity module: register/login/2FA/password reset behind `/api/v1/auth/*`.

**Phase 2: Read side (low risk)**
- Catalog, categories, search, reputation, content/SEO/i18n read endpoints.
- Public site pages call the API instead of `models/*` directly.

**Phase 3: Orders and Payments (high risk, last and slowest)**
- Port Payments + Escrow + Wallet as Laravel services/jobs, behind interfaces
  (`PaymentGateway`, `AddressDeriver`, `EscrowService`).
- Scheduler replaces `cron/*.php`; queue replaces `JobRunner`.
- Dual-run in shadow mode: Laravel computes results next to legacy and diffs them.
  Cutover per flow behind a feature flag, with instant rollback to legacy.

**Phase 4: Remaining modules**
- Messaging, Community, Admin, Settings. Port admin panel to the API.

**Phase 5: Developer platform and packaging**
- Developer portal, webhooks, usage dashboards, installer, docs, Docker/compose,
  hardening review (secrets out of repo, CSP, rate limits, audit log).

**Phase 6: Retire legacy**
- Remove legacy routes/files once every group is cut over and the shadow diff is clean.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Money logic regresses | Characterization tests, shadow mode, feature flags, per-flow rollback |
| Data drift between two apps | Single DB, Laravel migrations only, legacy writes stay untouched until cutover |
| Secrets in repo (`config/*.php`, keys) | Move to `.env`, rotate any key found in the repo |
| 12-language i18n | Port `lang/*.php` arrays to Laravel `lang/` unchanged |
| Large legacy surface (~320 files) | Strangler by module; no big-bang rewrite |

## Open decisions for the site owners

1. Laravel version (recommend current LTS-compatible release supported by PHP 8.3).
2. Tenancy: one shop per install (recommended for plug and play) vs multi-tenant.
3. Which currencies stay: BTC and XMR payments, plus card/PSP for legal shops?
4. Frozen items above: confirm they are dropped, not ported.
