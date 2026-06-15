# Local development environment

Local stack runs in Docker via [Laravel Sail](https://laravel.com/docs/sail). One `compose.yaml`, six services.

## Services

| Service | Image | Container hostname | Host-exposed port | Notes |
|---|---|---|---|---|
| `laravel.test` | sail-8.4/app (built locally) | `laravel.test` | 80 (`APP_PORT`), 5173 (Vite) | PHP 8.4 + nginx + composer + node 24 |
| `mysql` | `mysql:8.4` | `mysql` | 3306 (`FORWARD_DB_PORT`) | Persistent volume `sail-mysql` |
| `redis` | `redis:alpine` | `redis` | 6379 (`FORWARD_REDIS_PORT`) | Used for cache + queue |
| `mailpit` | `axllent/mailpit:latest` | `mailpit` | 1025 SMTP, 8025 dashboard | Catches outbound mail |
| `minio` | `minio/minio:latest` | `minio` | 9000 S3 API, 8900 console | Sail/password by default |
| `minio-createbuckets` | `minio/mc:latest` | — | — | One-shot sidecar; creates `AWS_BUCKET` then exits |

## Why these choices

- **MySQL 8.4** matches production. SQLite is only used by the test runner via `phpunit.xml` (`DB_CONNECTION=sqlite`, in-memory).
- **Redis** for cache + queue from day one — production parity for queue payloads, atomic-lock semantics, and rate-limiter behaviour.
- **Mailpit** catches mail without touching SES sandbox. Open http://localhost:8025 to read. SMTP on `mailpit:1025`.
- **Minio + sidecar** gives a real S3 PUT/GET surface for the upload flow. Critical because the upload module relies on presigned URLs — mock filesystems would let bugs hide. The `minio-createbuckets` container runs `mc mb` once on first healthy boot; idempotent on subsequent `sail up`.

## Env

Keep these in sync between `.env` and `.env.example`:

| Var | Local default | Notes |
|---|---|---|
| `DB_HOST` | `mysql` | Container hostname, not `127.0.0.1` |
| `DB_DATABASE` | `share` | |
| `DB_USERNAME` / `DB_PASSWORD` | `sail` / `password` | |
| `REDIS_HOST` | `redis` | |
| `MAIL_HOST` | `mailpit` | |
| `MAIL_PORT` | `1025` | |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | `sail` / `password` | Same as Minio root creds |
| `AWS_DEFAULT_REGION` | `us-east-1` | Minio ignores region but the SDK requires one |
| `AWS_BUCKET` | `share` | Auto-created by `minio-createbuckets` |
| `AWS_ENDPOINT` | `http://minio:9000` | App-side: container DNS |
| `AWS_URL` | `http://localhost:9000/share` | Browser-side: presigned URLs returned to the client |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` | Required for Minio |

`AWS_ENDPOINT` vs `AWS_URL`: the app dispatches presign calls *inside* the Docker network using `AWS_ENDPOINT`. The signed URL it returns to the browser must resolve from the host, so the SDK swaps the host using `AWS_URL` for the public-facing URL. Mismatched here = signature errors when the browser tries to PUT.

In production these all flip to real AWS credentials, real bucket, no `AWS_ENDPOINT`, no path-style.

## Resetting

```bash
sail down -v          # nuke containers + volumes (mysql data, minio data)
rm -rf storage/framework/cache/data/*
sail up -d            # fresh stack
sail artisan migrate
```

## Host-only mode (no Docker)

Possible but not the default. You'd need local mysql, redis, mailpit (or a real SMTP), and minio (or a dev AWS bucket). The `composer dev` script in `composer.json` boots the app server + queue worker + log tail + vite together.
