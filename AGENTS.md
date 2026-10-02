# Agent guide

Read [HANDOVER.md](HANDOVER.md) first: current state, architecture rules, gotchas and the
ordered backlog. Keep it updated at the end of every work session.

Rules:
- Modular monolith under `app/Modules/*`; thin controllers; logic in `Actions/*`.
- API-first: every capability is a documented `/api/v1` endpoint before any UI uses it.
- Payments/escrow: never change behaviour without tests that pin it first.
- Run `php artisan test` before finishing; regenerate `docs/openapi.json` when routes change.
- Do not install extra dev tooling (e.g. Laravel Boost) unless the owner asks.
