# Asansor Backend

Laravel 12 backend foundation for the Asansor maintenance and service management platform.

This application is currently limited to infrastructure setup:

- PHP 8.3+
- Laravel 12
- PostgreSQL
- Redis for cache, queue, and sessions
- Laravel Sanctum for API authentication foundation
- Laravel Reverb for realtime broadcasting foundation

No business modules, domain migrations, API endpoints, or business controllers are implemented at this stage.

## Local Development

From the repository root:

```bash
docker compose up -d --build
```

The backend is served at:

```text
http://localhost:8000
```

Health check:

```text
http://localhost:8000/up
```

Run tests:

```bash
docker compose exec app php artisan test
```
