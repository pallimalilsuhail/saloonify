# Saloonify MVP — Plan (final 2026-05-26)

## Context

Saloonify = multi-tenant SaaS for salon / beauty parlor management, UAE-first. User restarted planning 2026-05-23 (prior attempt unfinished). Pivot on 2026-05-26 to **mobile-first PWA** with tight v1.0 cut to POS only.

Why mobile-first: owners + staff in UAE all carry decent phones with reliable internet most of the time. No desktop admin UI needed v1.

Why thin Flutter shell later (v1.1): native distribution + push + Bluetooth printer, but server-side PWA updates so feature changes ship without app-store delay. v1.0 = pure installable PWA via "Add to Home Screen".

Why tight v1.0 cut: ship fast, validate POS flow with one real salon, then expand.

Authoritative spec: `/Users/suhailpallimalil/code/saloonify/requirements.md`.

---

## Locked decisions (carry-over + new)

From prior memory (unchanged):

- Stack: **Laravel 13 + PHP 8.3 + MySQL 8 + Livewire 4 + Volt + Flux + Tailwind 4 + Alpine.js**. Queue = database driver v1.0 (Horizon + Redis added when scale demands). Pattern lifted from proven `/Users/suhailpallimalil/code/share` project.
- App layout: **vertical-slice modules** under `app/Modules/<Module>/` (Http/Controllers, UseCases, Enums, Exceptions, Models). Shared primitives under `src/Shared/` (Contracts, Enums, Traits, ValueObjects). Routes split into `routes/web.php`, `routes/auth.php`, etc.
- Multi-tenant day 1: `business_id` on every tenant row + `BusinessScope` Eloquent global scope.
- UAE-only **at the salon-onboarding level v1.0** (`country='AE'`, `currency='AED'`, `tax_rate=5.00`), but **values stored as columns on `businesses`** so calculations are per-business and future country/currency/tax-rate extensions = data change, not schema change. TRN mandatory at onboarding (15 digits).
- **3 fixed roles v1.0**: `super_admin` (Saloonify staff, cross-business), `business_admin` (one business, all locations, manager-level PIN gates), `location_agent` (the worker — stylist/cashier at one or more assigned locations, own sales only). Staff↔location is **many-to-many** via `location_user` pivot (a stylist can work at several branches). `location_admin` middle tier dropped v1.0 — manager actions all gated to `business_admin`. Hand-rolled enum + Laravel Gate.
- No public signup. Super-admin onboards businesses manually.
- All 22 §10 locked defaults (gender enum, sale-level discount, combo frozen at sale, etc.).
- **Money stored as integer minor units (fils)** in `bigint` columns. AED × 100 = fils. `moneyphp/money` natively works in minor units. All arithmetic in fils; format to display only at view layer. Rounding HALF_UP at sale-close, never on input.
- Phone validation via `giggsey/libphonenumber-for-php`.
- Hosting: AWS me-central-1, Forge + EC2 + RDS MySQL. ElastiCache Redis deferred until Horizon adopted.
- Error tracking: Sentry (backend + frontend).
- Dev tooling: Telescope, Pail (log tail), Pint (lint), Sail (Docker).

New this session (2026-05-26):

- **Mobile-first PWA v1.0**, no desktop-specific UI.
- **Flutter native shell deferred to v1.1.**
- **v1.0 scope cut** to POS + catalog + staff + sales report + digital bilingual receipt.
- **Refunds / voids / credit notes deferred to v1.1.** v1.0 ships without; first-salon mitigation = manual DB-side adjustment + paper credit note if needed week 1–2.
- **UI language v1.0 = English only.** Receipts still bilingual EN+AR (services carry `name_ar`, both render on receipt). No Vue i18n scaffold v1.0.
- **Timeline = unhurried.** Phase 8 polish gets full 5 days. No corner-cutting on UX or UAT.

Update (2026-06-14):

- **Salon onboarding = endpoint only** in MVP (no super-admin UI). SaaS owner calls one `POST` to create business + first location + `business_admin` (with password); a second endpoint adds locations on request. Self-serve admin UI is post-MVP (subscription-gated). Supersedes the "onboarding screens" framing below.
- **Chairs added to MVP.** Chair belongs to a location, optionally maps one default staff (overridable). Captured per **sale line** (`sale_lines.chair_id`), auto-filled from the stylist's default chair. Report adds chair utilization.
- **Basic inventory added to MVP** (was OUT). Manual stock only: `on_hand_qty` / `in_use_qty`, `reorder_threshold`, in-app low-stock flag. **No** auto-deduction on sale, no batch/expiry, no retail sale lines — those stay v1.1 (now "granular inventory"). Report adds inventory on-hand + low-stock list.
- **Authoritative product doc** is now `product-spec.md` (source of truth) → this `plan.md` → `tasks.md`.

---

## UI strategy

### v1.0 — Pure mobile-first PWA (Livewire-rendered)

- Single Laravel app, server-rendered via Livewire 4 + Volt single-file components. Flux UI kit for forms / tables / nav. Alpine.js for purely client-side interactions (modals, dropdowns, cart line edits before sync).
- Tailwind 4 config tuned for 320–430 px primary viewport. Tablet/desktop = bonus, not gated.
- Touch-first: large hit targets, bottom action bar for primary CTAs, swipe-friendly lists.
- Installable via "Add to Home Screen" (manifest + service worker for install only — **not** offline; online-only locked per §10.1-10).
- Disconnect = block-and-banner (Alpine listens to `online`/`offline` events), cart preserved in `localStorage` until reconnect, sale-close button disabled offline.
- Livewire round-trip latency: acceptable for catalog / staff / reports; for POS cart, do line edits client-side with Alpine, single Livewire submit on "Close sale".
- No separate admin web. business-admin uses same PWA; role gates (`super_admin`, `business_member`, etc. middleware — pattern from share project) surface different menu items.

### v1.1+ — Flutter native shell

- Thin Flutter app: splash → login screen → on success, load PWA URL inside `flutter_inappwebview`. Auth cookie passed via cookie store sync.
- Distribution: App Store + Play Store. Salon installs once, all feature updates ship server-side.
- Unlocks: push notifications, Bluetooth ESC/POS printer, biometric login, camera/barcode for inventory.

---

## v1.0 MVP scope

**In scope:**

| Module                 | Detail                                                                                                                              |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Auth                   | **Plain Laravel auth** (Breeze with Livewire scaffold or hand-rolled). No WorkOS. Single login form with one "Email or username" field + password. Resolver: lookup user by `email`, fall back to `username`. For emailless staff, business-admin enters a username at creation; system auto-generates synthetic email `<username>@<business-slug>.local` to keep `users.email` always populated and unique. `pin_hash` column reserved, not gated v1.0. |
| Tenancy                | `TenantContext` middleware sets business on container. `BusinessScope` auto-applied via `BelongsToBusiness` trait. Super-admin bypass. |
| Salon onboarding (**endpoint only**) | SaaS owner `POST`s to create business (name, TRN 15-digit, defaults AE/AED/5%) + first location (address JSON, opening hours JSON) + `business_admin` user (with password) in one transaction. Second endpoint adds locations on request. No super-admin UI in v1.0. |
| Staff CRUD             | business-admin creates users with role + location assignment. Status machine `active`/`on_leave`/`terminated` (terminated = terminal, blocks login). Last-active-admin guard. Commission overrides deferred to v1.1 (default-only v1.0). |
| Chairs                 | Chair belongs to a location; optional default staff (one chair per staff, overridable). Captured per sale line (`sale_lines.chair_id`), auto-filled from the line's stylist. Inactive chairs not assignable. |
| Inventory (basic)      | Manual stock per item: `on_hand_qty` / `in_use_qty`, `reorder_threshold`. Actions: receive (+), mark in-use, mark finished. No sale linkage / auto-deduct. In-app low-stock flag. |
| Customer (light)       | Quick-add at sale time by mobile (libphonenumber UAE region). Name optional. Business-scoped, no cross-business linking.            |
| Services catalog       | `name` (English v1.0), `translations` JSON (locale-keyed, e.g. `{"ar":"..."}` — empty v1.0), `price`, `default_commission_pct`, `duration_minutes` (informational). |
| Combos catalog         | `name` + `translations` JSON, own `price` (not sum), `combo_commission_pct`, optional default primary stylist, constituent services with display order. |
| Walk-in POS            | Cart → add service / combo lines → assign stylist per line → discount (sale-level, % or fixed) → VAT 5% → payment (cash / card / split) → gender capture (`male`/`female`/`child`) → close → gapless invoice number via `SELECT … FOR UPDATE` on `invoice_counters`. |
| PDF invoice + WhatsApp | Every sale auto-renders PDF invoice on close, stored to S3 (or local disk staging). Receipt screen has single **"Send via WhatsApp"** button → opens `https://wa.me/<customer_mobile>?text=<invoice-link>` deep link, customer mobile pre-filled from CRM record. No thermal printing v1.0. |
| Sales report           | Date range, location + stylist + payment-method filters, totals (gross / discount / tax / net), per-payment-method split, per-stylist commission accrued, paginated line detail, CSV export. |
| Commission calc        | Auto-post `CommissionEntry` rows on sale-close (per-service % default, override matrix exists in schema but unused v1.0). No settlement workflow — just visible in report. |

**Out of v1.0 (deferred):**

- Granular inventory (auto-deduct on sale, batch/expiry, retail sale lines) → v1.1. *Basic manual inventory IS in v1.0.*
- Refunds / voids / credit notes → v1.1
- Thermal ESC/POS printing → v1.1
- EOD cash close → v1.1
- Flutter native shell → v1.1
- Appointments → v1.2
- Multi-stylist combo split → v2
- Vue i18n / AR UI → v1.1+

---

## v1.1 — next milestone (after v1.0 GA)

Suggested order:

1. **Refunds + voids + credit notes** — same-day void auto-reverses commission entries; manager-PIN refund flow; credit note own numbering series.
2. **Thermal printing** — `mike42/escpos-php` server render, bilingual template, Epson TM-T82 + Xprinter XP-58 over LAN. Bluetooth printer waits for Flutter shell.
3. **EOD cash close** — per-location, expected vs counted cash, variance + manager confirm.
4. **Granular inventory** — extend basic inventory with `StockMovement` (received / taken / per-batch expiry), auto-deduct on sale, retail sale lines (`SaleLine.line_type=retail_item`), FIFO expiry report.
5. **Flutter shell** — login + WebView wrapper, App Store + Play Store submission.

---

## v1.2+ — later

- Appointments / booking
- Push notifications (via Flutter shell)
- Multi-stylist combo commission split
- AR UI (full Vue i18n scaffold)
- Prepaid packages / khaata (still OUT v1 family — needs separate scoping)
- Per-business invoice template editor UI

---

## Phased build plan — v1.0

Each phase ends with a deployable state. Estimate 4–6 working weeks at unhurried pace.

### Phase 1 — Bootstrap (1 day)

- **Copy share project as starting point**, not `composer create-project`. `rsync -av --exclude='.git' --exclude='vendor' --exclude='node_modules' --exclude='.env' /Users/suhailpallimalil/code/share/ /Users/suhailpallimalil/code/saloonify/`. Reinit git, set new `APP_NAME=Saloonify`.
- Scrub share-specific code: drop `app/Modules/Documents`, `DocumentRequests`, `Sample`. Keep `Businesses`, `Common`, `Logger`, `AuditLog` as templates. Drop `laravel/workos` from composer + all `WorkOS*` references in `routes/auth.php` (rewrite for plain Breeze-with-Livewire login).
- Local: MySQL 8. Staging: RDS MySQL on Forge.
- Install **Laravel Breeze with Livewire (Volt) starter** for auth scaffold + base layouts.
- Install Composer deps: `livewire/livewire ^4`, `livewire/volt`, `livewire/flux`, `avoqado-dev/laravel-usecase`, `giggsey/libphonenumber-for-php`, `moneyphp/money`, `sentry/sentry-laravel`, `barryvdh/laravel-dompdf` (or `spatie/browsershot` — decide Phase 6), `simplesoftwareio/simple-qrcode` (already used in share, useful for invite links).
- Install NPM deps: `tailwindcss ^4`, `@tailwindcss/vite`, `alpinejs`, `@sentry/browser`, `vite`.
- Dev deps: `pestphp/pest ^4`, `pestphp/pest-plugin-laravel`, `laravel/pint`, `laravel/telescope`, `laravel/pail`, `laravel/sail`, `barryvdh/laravel-ide-helper`.
- Set up vertical-slice layout: create `app/Modules/` and `src/Shared/` dirs. Update `composer.json` autoload with `"Shared\\": "src/Shared/"`.
- Split routes: `routes/web.php`, `routes/auth.php`, `routes/settings.php` (mirror share project).
- PWA manifest + service worker stub (install only, no offline cache).
- GitHub Actions: Pint (lint), Pest (tests). Staging auto-deploy on `main` merge via Forge. Prod = manual approval.
- Sentry wired (backend `sentry-laravel` + frontend `@sentry/browser`).

### Phase 2 — Auth, tenancy, roles (2–3 days)

- Customize Breeze login Livewire/Volt component: single "Email or username" field + password. `authenticate()` resolver: try `Auth::attempt(['email' => $input, ...])` first; on miss, lookup user `where('username', $input)` and retry attempt with that user's email. Stock Laravel session auth (cookie-based) — no Sanctum API tokens needed v1.0.
- `users` migration: `business_id` (nullable for super_admin), `role` enum, `name`, `email` (unique, always populated — synthetic for emailless staff), `username` (nullable, unique), `password`, `pin_hash` (nullable, reserved), `status` enum (`active`/`on_leave`/`terminated`). **No `location_id`** — staff↔location is the `location_user` pivot.
- `location_user` pivot: `user_id`, `location_id`. Unique(user_id, location_id). `location_agent` has ≥1 row; `business_admin` none (spans all); `super_admin` none.
- Synthetic email helper in `app/Modules/Staff/Support/SyntheticEmail.php`: `make(string $username, string $businessSlug): string` → returns `"{$username}@{$businessSlug}.saloonify.local"`.
- Role middleware: lift the `share` project's middleware pattern, but for the 3 fixed roles — `super_admin`, `business_admin`, `location_agent`. Define route groups using same approach.
- Pest tests: cross-business read leak → fails. Role gate denials → correct. Login via email works. Login via username (with synthetic email user) works.
- `UserRole` PHP enum + Laravel Gate policies (3 fixed roles).
- `TenantContext` middleware resolves business from authenticated user, stashes on container.
- `BelongsToBusiness` trait carries `BusinessScope` global scope, auto-applied to every tenant model.
- Super-admin bypass: scope skipped when `Auth::user()->role === super_admin`.
- Pest tests: cross-business read leak → fails. Role gate denials → correct.

### Phase 3 — Core schema (1–2 days)

Forward-only migrations for v1.0 entities only:

- `businesses` — name, slug, trn (15 digits, app-layer validated), `country` char(2) default `'AE'`, `currency` char(3) default `'AED'`, `tax_rate` decimal(5,2) default `5.00`, invoice_template_settings_json
- `locations` — business_id, name, address_json, opening_hours_json
- `users` (from Phase 2)
- `customers` — business_id, mobile_number, name nullable. Unique on (business_id, mobile_number).
- `services` — business_id, name, translations json, price `bigint`, default_commission_pct `decimal(5,2)`, duration_minutes
- `combos` — business_id, name, translations json, price `bigint`, combo_commission_pct, default_primary_stylist_user_id nullable
- `combo_services` — combo_id, service_id, display_order
- `staff_service_commissions` — user_id, service_id, commission_pct (override only, NULL = inherit)
- `chairs` — business_id, location_id, name, is_active (default true), default_staff_user_id nullable (unique — one chair per staff). Unique(location_id, name).
- `inventory_items` — business_id, location_id, name, category nullable, on_hand_qty (default 0), in_use_qty (default 0), reorder_threshold (default 0). Manual only; no movement table v1.0.
- `sales` — business_id, location_id, customer_id nullable, cashier_user_id, customer_gender enum, subtotal, discount, discount_type, tax, total (all `bigint`), invoice_number, invoice_series, invoice_pdf_path, closed_at. Composite index `(business_id, location_id, closed_at)`.
- `sale_lines` — sale_id, line_type enum (`service`/`combo`), service_id nullable, combo_id nullable, combo_snapshot_json nullable, stylist_user_id nullable, `chair_id` nullable (auto-filled from stylist's default chair, overridable), quantity, unit_price `bigint`, line_total `bigint`
- `payments` — sale_id, method enum (`cash`/`card`), amount `bigint`
- `invoice_counters` — business_id, series, next_value
- `commission_entries` — business_id, sale_line_id, user_id, amount `bigint`, posted_at, reversal_of nullable
- `audit_logs` — business_id, user_id, action, entity_type, entity_id, before_json, after_json, at

All money columns: `bigint unsigned` storing fils (AED minor units, 1 AED = 100 fils). moneyphp/money operates natively in minor units. Display formatting only at view layer. Rounding HALF_UP at sale-close, never on input.

`translations` JSON shape: `{"<locale>": "<string>"}`. v1.0 leaves it `{}` or unpopulated. AR added v1.1+. Accessor merges `name` (primary EN) + locale lookup at render.

### Phase 4 — Onboarding (endpoint) + staff (1–2 days)

- **Super-admin onboarding = endpoint only, no UI.** `POST /api/admin/businesses` (super_admin guard) creates business + first location + `business_admin` user (with password) in one transaction; `POST /api/admin/businesses/{business}/locations` adds locations on request. Idempotency key supported.
- business-admin: staff create (name, **email and/or username** — at least one, password, role dropdown, **multi-location assign** via `location_user`). At-least-one rule + last-active-admin guard + status machine + (location_agent ≥1 location).

### Phase 5 — Catalog + chairs + inventory screens (3–4 days)

- Services list (mobile card layout) + create / edit (`name`, price in AED converted to fils on save, default_commission_pct, duration). `translations` field present but hidden v1.0.
- Combos list + create / edit (`name`, price, combo_commission_pct, multi-select services with display order).
- **Chairs**: list + create / edit per location, optional default staff (one chair per staff, overridable), activate/deactivate.
- **Inventory (basic)**: item list + create / edit, stock actions (receive / mark in-use / mark finished), reorder threshold, low-stock flag.
- business-admin scoped (location_agent may update inventory stock).

### Phase 6 — POS flow (5–7 days, heaviest)

- Customer quick-add modal (mobile field with libphonenumber autoformat, name optional).
- Cart screen: add service / combo line, assign stylist per line, edit quantity. Chair auto-fills from the stylist's default chair (overridable per line, active chairs only).
- Discount entry (sale-level, % or fixed, sanity bounds).
- VAT 5% auto-calc on net.
- Gender selector (3-button: male / female / child), required to close.
- Payment screen: cash / card / split. Sum must equal total.
- Sale-close: single DB transaction → `SELECT … FOR UPDATE` on `invoice_counters` → assign invoice_number → insert sale + lines + payments → post `commission_entries` → audit log → queue `RenderInvoicePdf` job → return receipt URL.
- Use case: `app/Modules/Sales/UseCases/CloseSale/` (Mediator Handler + Request DTO, per share-project layout).
- `RenderInvoicePdf` job: builds invoice (English-only v1.0, AR slot ready via `translations`), saves PDF to storage, writes path to `sales.invoice_pdf_path`. Library: `barryvdh/laravel-dompdf` or `spatie/browsershot` (Browsershot preferred for fidelity if Chromium available on host).
- POS implemented as Volt page component(s) under `resources/views/livewire/pages/pos/` — cart, payment, receipt. Cart-line client-side state via Alpine for snappy add/remove; single Livewire submit on "Close sale".
- Receipt screen: business name + TRN, invoice number, line items, subtotal / VAT / total in AED. Single CTA: **"Send via WhatsApp"** → Alpine handler builds `https://wa.me/<customer.mobile_number minus +>?text=<encoded message + PDF link>` and `window.open()`s it. Customer mobile pulled from sale's linked `Customer`; if no customer captured, prompt for number inline before opening WhatsApp.

### Phase 7 — Sales report (2–3 days)

- Date range picker (default: today).
- Filters: location, stylist, payment method.
- Sections: totals row, per-payment-method breakdown, per-stylist commission accrued, **per-chair utilization**, **inventory on-hand + low-stock list**, paginated line detail (incl. stylist + chair), CSV export.
- Server-side aggregation. Composite index on `sales(business_id, location_id, closed_at)` carries the report query. Date defaults to today in **Asia/Dubai**.

### Phase 8 — Mobile UX polish + UAT (5 days)

- Add-to-home-screen prompt UX.
- Disconnect block-and-banner + retry queue in browser memory.
- Loading, empty, error states across all screens.
- Seed/demo data script for UAT salon walkthroughs.
- Manual UAT with user. Pilot salon, full sale → report cycle.

### Phase 9 — Production cutover (1–2 days)

- Prod environment: Forge + EC2 me-central-1, RDS MySQL, ElastiCache Redis.
- Migrations + first business onboarding.
- Monitoring sanity (Sentry, Pulse, CloudWatch alarms on RDS CPU / connection count).
- v1.0 GA.

---

## Critical files (post Phase 1)

- `/Users/suhailpallimalil/code/saloonify/requirements.md` — frozen spec.
- `app/Modules/Sales/UseCases/CloseSale/Handler.php` + `Request.php` — sale-close transaction.
- `app/Modules/Sales/Models/Sale.php`, `SaleLine.php`, `Payment.php` — module-scoped models.
- `src/Shared/Concerns/BelongsToBusiness.php` — trait carrying `BusinessScope` global scope.
- `app/Http/Middleware/TenantContext.php` — sets tenant on container.
- `app/Http/Middleware/SuperAdmin.php`, `BusinessAdmin.php`, `LocationAgent.php` — role gates (pattern lifted from share project).
- `app/Modules/Businesses/Enums/UserRole.php` — 3 fixed roles enum (`SuperAdmin`, `BusinessAdmin`, `LocationAgent`).
- `app/Modules/Staff/Support/SyntheticEmail.php` — synthetic email generator for emailless staff.
- `resources/views/livewire/pages/pos/cart.blade.php` — primary POS Volt page.
- `resources/views/livewire/pages/pos/payment.blade.php` — payment + close.
- `resources/views/livewire/pages/receipt/show.blade.php` — receipt + WhatsApp CTA.
- `resources/views/livewire/pages/reports/sales.blade.php` — sales report.
- `resources/views/layouts/mobile.blade.php` — mobile-first shell with bottom action bar.
- `app/Jobs/RenderInvoicePdf.php` — async PDF render on sale-close (queue:listen v1.0).
- `resources/views/invoices/pdf.blade.php` — PDF template.

---

## Verification

Each phase ends green when:

- **Phase 1**: `composer install` + `npm run build` clean. Staging deploy succeeds. Sentry test event received.
- **Phase 2**: Pest cross-tenant + role-gate tests pass. Manual login round-trip works on phone viewport.
- **Phase 3**: `php artisan migrate:fresh` clean. Schema dump matches spec.
- **Phase 4**: Super-admin creates business + location end-to-end. TRN validation rejects 14 / 16 digit inputs.
- **Phase 5**: business-admin creates a service + combo. Combo snapshot field populates correctly on first sale.
- **Phase 6**: Walk-in sale closes, invoice number gapless across concurrent close attempts (load test 2 workers × 50 sales). Receipt renders bilingual.
- **Phase 6**: PDF renders within 5s of sale-close; WhatsApp deep link opens correctly on iOS + Android.
- **Phase 7**: Report totals match raw SQL aggregate exactly (integer fils, no rounding drift). CSV downloadable.
- **Phase 8**: Pilot salon staff completes 5 real sales unassisted from phone.
- **Phase 9**: First prod sale closes. Sentry quiet. Pulse healthy.

---

## What this plan deliberately excludes

- Inventory, refunds, thermal printing, EOD close, appointments, Flutter shell, AR UI → all v1.1+ per decisions above.
- Custom roles, multi-currency, multi-tax, prepaid packages, tipping, public signup → still OUT v1 family per original lock.
- Stack re-evaluation.
- Re-litigation of §10 defaults.
