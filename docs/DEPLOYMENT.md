# Deployment guide

A production install needs PHP 8.3, MySQL 8, Node 22 (build only), a web server with HTTPS,
a queue worker and a scheduler. Everything secret lives in `.env`, never in git.

## 1. Server setup

```bash
git clone <repo> /var/www/dm && cd /var/www/dm
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

Edit `.env`:

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | your https URL (no path prefix) |
| `DB_*` | MySQL 8 credentials (dedicated user, utf8mb4) |
| `QUEUE_CONNECTION` | `database` (or redis) |
| `SESSION_SECURE_COOKIE` | `true` |
| `MAIL_*` | real SMTP for password reset and verification |
| `BTC_XPUB`, `BLOCKCYPHER_TOKEN`, `BTC_MIN_CONFIRMATIONS` | Bitcoin receiving (see `config/payments.php`) |
| `MONERO_*` | wallet-rpc URL and credentials (see `config/monero.php`) |

Then:

```bash
php artisan migrate --force
php artisan passport:keys
php artisan passport:client --personal --name="Web" --provider=users
php artisan db:seed --force          # default categories only
php artisan admin:create owner@example.com
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Point the web server document root at `public/`. Keep `storage/` and `bootstrap/cache/`
writable by the PHP user only.

## 2. Background processes (required)

Payments are detected by the scheduler and webhooks are sent by the queue. Without these,
orders never move from "awaiting payment" and webhooks never fire.

```cron
* * * * * cd /var/www/dm && php artisan schedule:run >> /dev/null 2>&1
```

Run a supervised worker (systemd or supervisor): `php artisan queue:work --tries=3 --max-time=3600`.

## 3. Before real money (go-live gates)

1. CI is green on **SQLite and MySQL** (`.github/workflows/ci.yml`).
2. Bitcoin **testnet** and Monero **stagenet**: place an order, pay it, watch it become
   `held`, ship, confirm, check the vendor wallet, request and approve a payout.
3. Independent security review of the payment, wallet and admin code paths.
4. HTTPS only, HSTS on, `APP_DEBUG=false`, admin accounts have 2FA enabled.
5. Backups: nightly database dump + off-site copy, restore tested.
6. Legal pages reviewed by a lawyer for the operator's jurisdiction (they are templates).

## 4. Operations

- Logs: `storage/logs/` (payment audit log: `storage/logs/payments-*.log`).
- Health: `GET /up`.
- Deploy: `git pull && composer install --no-dev -o && npm ci && npm run build && php artisan migrate --force && php artisan config:cache route:cache view:cache && php artisan queue:restart`.
- Rotate keys: changing `APP_KEY` invalidates encrypted columns (addresses, messages, 2FA
  secrets). Plan a re-encryption step before rotating.
