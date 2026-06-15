# Observability

Five layers, ported from `/Users/suhailpallimalil/code/academyfy`.

| Layer | Tool | Where it lives |
|---|---|---|
| Error tracking (backend) | `sentry/sentry-laravel` → Sentry / GlitchTip | `config/sentry.php`, `bootstrap/app.php` |
| Error tracking (browser) | `@sentry/browser` | `resources/js/app.js` |
| Structured logs | Custom Logger module | `app/Modules/Logger/` |
| Request tracing | `RequestTracingMiddleware` | `app/Modules/Logger/Middleware/` |
| Dev query monitor | `laravel/telescope` | `/telescope` route |
| Log shipping (prod) | Grafana Alloy → Loki | `install-promtail.sh` |

## Backend errors — Sentry

`bootstrap/app.php` calls `Integration::handles($exceptions)` so every uncaught exception is reported. `/up` is excluded from transaction tracing (see `config/sentry.php` `ignore_transactions`).

PII is **off by default** (`SENTRY_SEND_DEFAULT_PII=false`). The `UserProcessor` already attaches `user_id` + `workos_id` to log records; we don't need Sentry to scrape them from the request.

Breadcrumbs captured: SQL queries (no bindings), cache hits/writes, queue jobs, HTTP client requests, notifications, Livewire components, Laravel logs.

Tracing sample rate defaults to **0** — flip selectively (`SENTRY_TRACES_SAMPLE_RATE=0.1`) when investigating performance.

## Browser errors

`resources/js/app.js` initialises `@sentry/browser` only if `VITE_SENTRY_DSN` is set. Same per-env rate config as backend. Compatible with Livewire — captures uncaught JS errors regardless of Volt component lifecycle.

## Structured logs

`LoggerServiceProvider` overrides Laravel's `single` and `daily` channels to:
- Output to `storage/logs/laravel.json` (note: JSON, not `.log`)
- Use `JsonFormatter` (one JSON object per line)
- Tap `EnhancedContextProcessor` which composes:
  - `TraceIdProcessor` — adds `request_id` from `Context`
  - `UserProcessor` — adds `user_id` (and `workos_id` if present)
  - `RequestProcessor` — HTTP method, URL, IP
  - `SourceProcessor` — file + line
  - `PerformanceProcessor` — memory + execution time

Domain events are auto-logged by `EventLoggingSubscriber` at `info` level (`event_name` + `event_data`). Framework events (`Illuminate\\*`, `Laravel\\*`, `eloquent.*`) are excluded — see `app/Modules/Logger/config/logger.php`.

## Request tracing

`RequestTracingMiddleware` is **prepended** so it wraps everything (including auth, exception handling). It:
1. Reads `X-Request-Id` (falls back to `X-Correlation-Id`, then `X-Trace-Id`)
2. Generates a UUID if none provided
3. Stores in `Context::add('request_id', $id)` so all log lines + Sentry breadcrumbs include it
4. Echoes `X-Request-Id: ...` on the response so callers can correlate

Use this header when filing bug reports — search the log line in Loki by `request_id` to get every event in that request.

## Telescope (dev only)

Mounted at `/telescope`, auth-gated (only the original user during local dev — adjust `app/Providers/TelescopeServiceProvider.php` for staging access).

In production set `TELESCOPE_ENABLED=false` (default in `.env.example`).

## Log shipping (prod)

`install-promtail.sh` runs **on the production server** (Forge / EC2):
1. Auto-detects amd64/arm64
2. Downloads Grafana Alloy v1.6.1
3. Generates `/home/forge/$APP_DOMAIN/alloy/config.alloy`
4. Watches `storage/logs/laravel.json`, ships to Loki with labels `job=laravel`, `env=$APP_ENV`, `namespace=$NAMESPACE`, `app=php`
5. Registers as a systemd / supervisor service

Required env: `LOKI_URL`, `LOKI_USERNAME`, `LOKI_PASSWORD`, `NAMESPACE`. Skip locally — log file on disk + Telescope is enough.

## Local dev workflow

- Errors in dev: visible at `/telescope/exceptions`
- Logs: `tail -f storage/logs/laravel.json | jq` (jq pretty-prints)
- Request id: included on every response — copy from the `X-Request-Id` header to grep logs

## Testing

- `tests/Feature/Logger/RequestTracingMiddlewareTest.php` — UUID generation + header propagation
- `tests/Unit/Logger/JsonFormatterTest.php` — formatter output shape
