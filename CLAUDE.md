# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Asansör (elevator) maintenance and service management system for a single maintenance company (multi-tenant/SaaS is an explicit future possibility, not current scope — see `SOLUTION_ARCHITECTURE.md` section 14). Monorepo with `backend/` (Laravel 12, implemented), `web/` (React 18 + Vite + Tailwind/shadcn-style UI kit; all pages currently render mock data from `web/src/mock/` — not yet wired to the API) and `mobile/` (placeholder only — no code yet). Web commands from `web/`: `npm run dev` (port 5173), `npm run build` (typecheck + build), `npm run lint` (tsc only).

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

Implemented features:

- **Auth**: `AuthController` (`Api/V1`) — `login` (email/password → Sanctum token, throttled 5/min per email+IP), `logout`, `me`.
- **CRUD** for Building, Elevator, ServiceContract, WorkOrder and User under `/api/v1` (apiResource routes). All follow the same pattern: thin controller + Form Request + `JsonResource`, UUID route binding, related records referenced by `*_uuid` in payloads (never internal ids) and validated against the caller's company (`Rule::exists()->where('company_id', ...)`).
- **Company scoping**: `CompanyScope` global scope + `BelongsToCompany` trait (auto-assigns `company_id` on create from the authenticated user; skipped when unauthenticated for console/seeder contexts). Client-supplied `company_id` is never fillable. Cross-company records 404 via route binding.
- **List endpoints**: `App\Support\ListQuery` applies the §12 conventions (`page`/`per_page` default 25 max 100, `filter[field]=value`, `filter[col_from]`/`filter[col_to]` date ranges, `sort=-field,other`, `search=`). Fields must be whitelisted per controller; unknown filter/sort fields return 422. Responses go through `ApiResponse::paginated()` (`meta.pagination = {page, per_page, total, total_pages}`). Use `ListQuery` for any new list endpoint.
- **Error contract**: global exception renderers in `bootstrap/app.php` map every API exception (validation 422, auth 401, HTTP 403/404/405/429, unexpected 500 with message hidden unless `app.debug`) to the `ApiResponse::error()` shape. Do not override `failedValidation()` in Form Requests — the global `ValidationException` renderer handles it.
- **Rate limiting**: `api` limiter (60/min per user, per IP when anonymous) applied to the whole API group; 429 responses keep `Retry-After` headers.

Not yet implemented: role-based authorization (spatie roles are seeded and assignable but no Policy/Gate checks exist — deliberately deferred until requirements are agreed with the customer), soft-delete restore flows, company self-management.

Default roles seeded by `DefaultRoleSeeder` (guard `web`): Super Admin, Company Owner, Manager, Technician, Office Staff, Customer.

### Conventions to follow (from `SOLUTION_ARCHITECTURE.md`)

- **API responses**: always via `App\Support\ApiResponse::success()` / `::error()` — do not hand-roll JSON responses. Success shape: `{success, data, message, meta}`; error shape: `{success, message, error: {code, details}}`.
- **Controllers stay thin**: request validation via Form Request classes (see `app/Http/Requests/Auth/LoginRequest.php`), business logic goes in Service/Action classes, not controllers.
- Company-scoped data ownership must be enforced backend-side (never trust client-supplied company scope) — relevant once multi-tenant-aware endpoints are added.
- REST resources under `/api/v1`, plural resource names. Pagination/filter/sort/search conventions are implemented by `App\Support\ListQuery` — reuse it instead of hand-rolling query param handling.
- PSR-12 formatting, Conventional Commits for commit messages.
- Repository pattern is *not* the default — only introduce it where there's a real persistence-abstraction or testability need; plain Eloquent for simple CRUD.
