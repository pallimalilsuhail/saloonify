# Saloonify

Multi-tenant SaaS that runs the front desk of a UAE salon / beauty parlor from a phone. v1.0 is a mobile-first walk-in **POS**: ring up services, assign the stylist + chair, take payment, issue a VAT-compliant invoice, send it on WhatsApp, and see daily sales + stylist commission.

## Docs

Planning + specs live in [`docs/`](docs/):

- [`product-spec.md`](docs/product-spec.md) — product & system spec (source of truth)
- [`data-model.md`](docs/data-model.md) — physical schema + business-rules registry
- [`plan.md`](docs/plan.md) — phased build plan + locked tech decisions
- [`tasks.md`](docs/tasks.md) — Feature → Issue → PR breakdown
- [`requirements.md`](docs/requirements.md) — original frozen requirements

## Stack

Laravel 13 · PHP 8.3+ · MySQL 8 · Livewire 4 + Volt + Flux + Tailwind 4 + Alpine · plain Breeze auth. Queue on the database driver. Hosting: AWS me-central-1 via Forge. Errors: Sentry.

## Local development

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
# MySQL via the bundled compose.yaml (sail) or any local MySQL 8
php artisan migrate:fresh
php artisan serve
```

App timezone is `Asia/Dubai`; money is stored as integer fils.
