# share

Secure document collection app for UAE insurance businesses. Replaces WhatsApp doc collection with expiring, presigned, audit-logged upload links.

## Stack

- Laravel 13 + PHP 8.4
- Livewire 4 + Volt (authed UI)
- Blade + Alpine.js + vanilla XHR (public uploader)
- Tailwind v4
- MySQL 8, Redis
- AWS S3 (direct-to-S3 presigned PUT/GET) — Minio in local dev
- Pest 4

## Architecture

Mirrors the Mediator/UseCase pattern from the upstream reference project. Modules under `app/Modules/{Domain}/`. Cross-cutting code in `src/Shared/{ValueObjects,Traits,Contracts}/`.

- `app/Modules/` — domain modules (Businesses, Customers, DocumentRequests, Documents, AuditLog, Onboarding)
- `src/Shared/` — `Id` (ULID), `Email`, `PhoneNumber`, `Token`, base traits, contracts
- `routes/` — split per domain, wired from `web.php`
- `resources/views/layouts/{app,public}.blade.php` — authed shell + public-uploader shell

See `.claude/docs/` for module + backend conventions.

## Local development (Sail / Docker)

Requires Docker Desktop.

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Add an alias for convenience:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

### Service map

| Service | Container | Host port | Purpose |
|---|---|---|---|
| App (php-fpm + nginx) | `laravel.test` | http://localhost | Laravel + Livewire app |
| Vite dev server | `laravel.test` | 5173 | Frontend HMR |
| MySQL 8.4 | `mysql` | 3306 | Primary DB |
| Redis | `redis` | 6379 | Cache + queue |
| Mailpit | `mailpit` | http://localhost:8025 | Outbound mail catcher (SMTP on 1025) |
| Minio | `minio` | http://localhost:9000 (S3) / http://localhost:8900 (console, sail/password) | S3-compatible blob store |
| Minio create-buckets | `minio-createbuckets` | — | Creates the configured `AWS_BUCKET` on first boot, then exits |

Bucket auto-creation: the `minio-createbuckets` sidecar runs once after Minio is healthy, creates the bucket named in `AWS_BUCKET` (default `share`), and sets it private. Re-run `sail up` is idempotent.

### Common commands

```bash
sail up -d              # start stack
sail down               # stop stack (keep volumes)
sail down -v            # stop and wipe volumes
sail artisan test       # run Pest
sail artisan migrate    # run migrations
sail npm run dev        # vite dev server
sail shell              # bash inside app container
sail mysql              # mysql client connected to local DB
sail redis              # redis-cli
```

## Tests

```bash
sail artisan test
# or, on host PHP:
vendor/bin/pest
```

## GitHub project

Issues, milestones (Phase 0–7), and labels: https://github.com/pallimalilsuhail/share/issues
