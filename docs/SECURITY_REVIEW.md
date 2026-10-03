# Security review

Independent application-security review of the whole codebase (Laravel 13, modular monolith).
All regression tests live in `tests/Feature/Security/SecurityReviewTest.php`.

## Scope and method

- Scope: every route (200 routes from `php artisan route:list`: `routes/web.php`, `routes/api.php`, module routes, Passport),
  all modules (Identity, Catalog, Orders, Payments, Wallet, Escrow, Admin, Messaging, Reputation, Community,
  DeveloperPlatform, ContentSeo), middleware, config, Blade views, dependencies.
- Method: manual adversarial code reading of controllers, policies, form requests, actions and models; route-by-route
  review of middleware (`auth:api`, `scopes:`/`scope:`, `role:`, throttles); grep for raw SQL, `{!! !!}`, `x-html`,
  secrets; `composer audit` and `npm audit`; each fix pinned by a regression test; full test suite run at the end.
- Not exhaustive for: every individual Community service branch and the Blade/Alpine views beyond a grep for
  unsafe sinks (none found besides the sanitised markdown component and JSON-LD, both safe by construction).

## Findings

| # | Severity | Location | Finding | Status | Test |
|---|---|---|---|---|---|
| 1 | High | `DeveloperPlatform` webhook URL validation, `DeliverWebhook` | SSRF: only enforced in production, only literal `localhost`/IP; hostnames resolving to private/metadata IPs, decimal/hex/short IPv4, IPv4-mapped IPv6, ULA, credentials, odd ports, redirects and DNS rebinding were all possible | Fixed: `WebhookUrlGuard` (resolves every A/AAAA record, rejects non-public), checked at save and again at delivery, redirects disabled, error text no longer echoes internal exception messages | `test_webhook_url_to_internal_targets_is_rejected` (14 cases), `test_webhook_hostname_resolving_to_private_address_is_rejected`, `test_delivery_rechecks_dns_and_does_not_send_to_private_address` |
| 2 | Medium | `PaymentController::show` | Vendor (order party) could read the buyer's deposit address; payment records could be created (consuming xpub derivation indices) for cancelled/paid orders | Fixed: buyer or admin only; creation only while `pending_payment` | `test_vendor_cannot_read_the_buyers_deposit_address`, `test_other_users_cannot_touch_an_order_its_payment_or_its_conversation` |
| 3 | Medium | `ProductController::update` | Vendor could set a moderator-archived product back to `active`, bypassing moderation | Fixed: archived-by-moderator products cannot be re-activated by the vendor (admin restore goes to draft first) | `test_vendor_cannot_reactivate_a_product_a_moderator_archived` |
| 4 | Medium | Mail links (password reset, verification) | Links built from the request Host header (reset-link poisoning) | Fixed: `URL::forceRootUrl(APP_URL)` in production. Operators must still set `APP_URL` correctly (and ideally trusted hosts at the proxy) | Not unit-testable without a production boot; verified by code review |
| 5 | Medium | `config/cors.php` (missing) | Framework default allowed every origin on `api/*` | Fixed: explicit config, same-origin only unless `CORS_ALLOWED_ORIGINS` is set, no credentials | `test_cors_does_not_reflect_arbitrary_origins` |
| 6 | Low | `AuthController::changePassword` | Other access/refresh tokens stayed valid after a password change | Fixed: every token except the current one is revoked | `test_changing_password_revokes_other_tokens` |
| 7 | Low | `PageController::locale` | Open redirect via attacker-controlled Referer | Fixed: only same-host return URLs, else home | `test_locale_switch_never_redirects_off_site` |
| 8 | Low | `CheckoutService` | Soft-deleted product in a cart caused a 500 (null dereference) | Fixed: clean 422 | `test_checkout_with_a_deleted_product_is_a_clean_422` |
| 9 | Low | `.env.example` | Defaults `APP_ENV=local`, `APP_DEBUG=true`, no secure cookie flag | Fixed: production-safe defaults, `SESSION_SECURE_COOKIE=true`; local dev must override | Review |
| 10 | Low | `PaymentService::poll` | Funds confirmed for an order that is no longer pending payment (cancelled after the buyer paid) were silently ignored | Mitigated: warning logged in the payments channel for manual reconciliation. No automatic refund | Review |
| 11 | Info | Passport | Password grant is not enabled (verified), client-credentials tokens carry no user so `auth:api` rejects them | Verified | `test_oauth_password_grant_is_disabled` |
| 12 | Info | Registration / profile | Role, `is_verified_vendor`, `email_verified_at` cannot be mass-assigned; `admin` role rejected | Verified | `test_register_and_profile_update_cannot_escalate` |
| 13 | Info | Scope/role middleware | A buyer holding every scope (browser cookie token) cannot reach admin/vendor routes | Verified | `test_buyer_token_with_every_scope_cannot_reach_vendor_or_admin_endpoints` |
| 14 | Info | Orders, disputes, payouts, webhooks, API keys | Cross-user access denied; payout overdraw, negative/float/overflow amounts rejected; reject refunds exactly once; dispute resolves once and vendor credited once; `markPaid` replay is a no-op | Verified | `test_payout_cannot_overdraw...`, `test_dispute_resolves_exactly_once...`, `test_webhooks_and_api_keys_are_owner_only` |
| 15 | Info | CSRF / cookies | `PreventRequestForgery` is in the web group, cookies HttpOnly and SameSite=lax | Verified | `test_web_group_has_csrf_protection_and_session_cookie_hardening` |
| 16 | Low | CSP | `script-src` allows `unsafe-inline`/`unsafe-eval` (Alpine, inline scripts) | Accepted/open: moves XSS defence entirely onto output encoding. Backlog: nonces + Alpine CSP build | n/a |
| 17 | Low | Registration | `unique:users,email` validation reveals whether an e-mail is registered (rate limited to 5/min per email+IP) | Accepted | n/a |
| 18 | Low | Login lockout | 5 failures lock the account for 15 min per e-mail, so an attacker can lock out a known e-mail (DoS) | Accepted (standard trade-off) | n/a |
| 19 | Low | Messaging | Any user can start a conversation with any user id (rate limited to 20/min, blocks respected) | Accepted | n/a |
| 20 | Low | OAuth device-code routes | Passport device grant routes are registered; not used by the product | Open: disable or document if unused |  n/a |

Counts: Critical 0, High 1 (fixed), Medium 4 (fixed), Low 9 (items 6-10 fixed or mitigated; 16-19 accepted; 20 open), Info 5 (verified). Existing test `test_payment_api_visibility_and_listener` was updated: the vendor now gets 403 on the payment endpoint by design.

## Dependencies

- `composer audit`: no advisories. `npm audit --omit=dev`: 0 vulnerabilities.

## Areas reviewed without findings

- SQL: no user input reaches raw SQL; all `LIKE` inputs are escaped; sorting is fixed server side.
- XSS: markdown renderer escapes first and emits a fixed tag set with `http(s)` links only; JSON-LD uses `JSON_HEX_*`; no `x-html`.
- Money: all mutations run in DB transactions with `lockForUpdate` and state re-checks (`OrderLifecycle`, `Ledger`, `PayoutService`,
  `DisputeService`, checkout stock); amounts are integers; ledger rows have unique idempotency keys; money fields are not mass assignable.
- Secrets: nothing secret committed (`.env`, Passport keys git-ignored; the only zpub in the repo is a public BIP84 test vector).
- Encrypted casts: shipping address, deposit address, 2FA secret, webhook secret, message bodies.
- 2FA: TOTP replay protection, atomic single-use recovery codes, lockouts, password-reset revokes sessions and tokens.

## Residual risks and what could NOT be verified

- Real-chain behaviour (Bitcoin testnet, Monero stagenet), confirmation depth, reorgs, underpayment/overpayment handling: only faked HTTP.
- Row-lock concurrency: tests run on SQLite, which cannot prove `lockForUpdate` races. Run the suite and a concurrency test on MySQL/InnoDB.
- Production boot behaviours (forced root URL, HTTPS, HSTS, trusted proxies, real mail delivery), infrastructure, WAF, backups, log shipping.
- Webhook SSRF: DNS is resolved by PHP before the request and again by curl; a very short-TTL rebinding race between those two lookups is
  theoretically possible. Egress filtering (firewall rule blocking RFC1918/metadata from the app host or a dedicated outbound proxy) is the proper final control.
- Device-grant and OAuth consent screens were not exercised in a browser.
- Admin accounts have no mandatory 2FA (banner only). Funds that arrive for a cancelled order need manual refund.
- APP_KEY rotation: encrypted columns and 2FA recovery-code HMACs depend on `APP_KEY`; rotating it without a re-encryption migration makes them unreadable. Back up the key and plan rotation with `APP_PREVIOUS_KEYS`.

## Go-live recommendations

1. Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS` only if needed, strong DB creds, real mail.
2. Run the full suite on MySQL and add a parallel-request test for checkout stock, payout, dispute resolution.
3. Testnet/stagenet end-to-end run; reconcile the "payment for non-pending order" log warning in monitoring.
4. Enforce 2FA for admins; consider disabling the unused device grant routes.
5. CSP nonces and Alpine CSP build; egress filtering for the queue worker (webhooks) and the rate-fetch URLs.
6. Queue worker + scheduler supervised, log shipping with alerting on the `payments` channel, offsite encrypted backups including `APP_KEY` and Passport keys.
7. Second review after any change to payments, wallet or escrow code.
