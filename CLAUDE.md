# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Asansör (elevator) maintenance and service management system for a single maintenance company (multi-tenant/SaaS is an explicit future possibility, not current scope — see `SOLUTION_ARCHITECTURE.md` section 14). Monorepo with `backend/` (Laravel 12, implemented), `web/` and `mobile/` (placeholders only — no code yet).

`SOLUTION_ARCHITECTURE.md` at the repo root is the authoritative design reference (domain model, API conventions, ADRs, coding standards). Read it before making architectural decisions — it is detailed and mostly still aspirational relative to current code, but its conventions (response format, naming, layering) apply to what does exist.

## Backend (`backend/`)

Laravel 12, PHP 8.3+, PostgreSQL, Redis (cache/queue/session), Sanctum (API tokens), Reverb (realtime, not yet used), spatie/laravel-permission (roles).

### Commands

Run via Docker from repo root, or directly inside `backend/` if you have a local PHP/composer set up:

```bash
docker compose up -d --build          # start app + postgres + redis
docker compose exec app php artisan test              # run full test suite
docker compose exec app php artisan test --filter=Name # single test
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed            # seeds default roles (DefaultRoleSeeder)
```

Without Docker, from `backend/`: `composer install`, `php artisan serve`, `php artisan test`. Tests run against in-memory SQLite (see `phpunit.xml`), not Postgres — no DB setup needed to run the suite. `composer test` clears config cache then runs `artisan test`.

Health check: `GET /up`. API is namespaced under `/api/v1` (see `routes/api.php`).

### Domain model implemented so far

```
Company (uuid, HasFactory/SoftDeletes) --< Building --< Elevator --< ServiceContract --< WorkOrder
User belongs to Company, uses HasRoles (spatie/laravel-permission), Sanctum tokens for API auth
```

Every model uses UUID primary identifiers (`HasUuids`, `uniqueIds()`) and soft deletes. `Elevator` additionally exposes a `qr_identifier` as a unique id (field-level QR/physical identity concept from the architecture doc). `WorkOrder` auto-generates a `work_order_number` (`WO-YYYYMMDD-XXXXXXXX`) in a `booted()` hook if not set.

Only auth is implemented as a feature: `AuthController` (`Api/V1`) with `login` (email/password → Sanctum token), `logout`, `me`. No CRUD endpoints for Building/Elevator/ServiceContract/WorkOrder exist yet despite models/migrations/factories being present — routes only cover `/api/v1/login`, `/api/v1/logout`, `/api/v1/me`.

Default roles seeded by `DefaultRoleSeeder` (guard `web`): Super Admin, Company Owner, Manager, Technician, Office Staff, Customer.

### Conventions to follow (from `SOLUTION_ARCHITECTURE.md`)

- **API responses**: always via `App\Support\ApiResponse::success()` / `::error()` — do not hand-roll JSON responses. Success shape: `{success, data, message, meta}`; error shape: `{success, message, error: {code, details}}`.
- **Controllers stay thin**: request validation via Form Request classes (see `app/Http/Requests/Auth/LoginRequest.php`), business logic goes in Service/Action classes, not controllers.
- Company-scoped data ownership must be enforced backend-side (never trust client-supplied company scope) — relevant once multi-tenant-aware endpoints are added.
- REST resources under `/api/v1`, plural resource names, standard pagination (`page`, `per_page`, default 25) and filter (`filter[field]=value`, `sort=field`/`-field`) conventions once list endpoints are built.
- PSR-12 formatting, Conventional Commits for commit messages.
- Repository pattern is *not* the default — only introduce it where there's a real persistence-abstraction or testability need; plain Eloquent for simple CRUD.
