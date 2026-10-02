# Dutch Market: multi-vendor marketplace (Laravel, API-first)

A multi-vendor marketplace platform. One deployment hosts many independent vendors; the
owner administers it. Everything is exposed through a versioned, documented JSON API
(`/api/v1`) secured with OAuth 2.0; the web dashboards are clients of that same API.

- **Stack:** Laravel 13, PHP 8.3, MySQL, Laravel Passport (OAuth 2.0), Scramble (OpenAPI)
- **Architecture:** modular monolith, code grouped per module under `app/Modules/*`
- **Status and next steps:** see [HANDOVER.md](HANDOVER.md)
- **Plan:** [docs/MIGRATION_PLAN.md](docs/MIGRATION_PLAN.md)
- **OpenAPI spec:** [docs/openapi.json](docs/openapi.json) (live docs at `/docs/api`)

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
# create a MySQL database and set DB_* in .env
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --name="Personal" --provider=users
php artisan test
php artisan serve         # docs at http://127.0.0.1:8000/docs/api
```
