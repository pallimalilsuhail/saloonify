# Saloonify v1.0 — Data Model & Business-Rules Registry

> **Source of truth for the physical schema and for every business rule's stable ID.**
> Hierarchy: `product-spec.md` (conceptual) → **this doc** (physical schema + rule registry) → `plan.md` → `tasks.md`. Migrations implement the tables below exactly; tasks reference tables by `§<table>` and rules by ID (e.g. `BR-SALE-02`). If a column or rule changes, change it **here** and the tasks inherit it.

---

## 1. Conventions (apply to every table)

- **Engine/charset**: InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
- **Primary key**: `id` `BIGINT UNSIGNED AUTO_INCREMENT`.
- **Timestamps**: `created_at`, `updated_at` (`TIMESTAMP NULL`) on all tables unless noted.
- **Naming**: table = `snake_case` plural; column = `snake_case`; foreign key = `<singular>_id`; boolean = `is_*`.
- **Money**: stored as integer **fils** (no column-name suffix — e.g. `price`, `total`, `amount`), type `BIGINT UNSIGNED`. Never decimal/float. The `MoneyFils` cast converts to/from AED at the model edge. (1 AED = 100 fils.)
- **Enums**: stored as `VARCHAR` + cast to a PHP enum in the model (not MySQL `ENUM`, so values evolve without `ALTER`). Allowed values listed in §3. Validation enforces membership.
- **JSON**: native MySQL `JSON` type; suffix `_json`.
- **Tenancy**: every business-owned table carries `business_id` (`BIGINT UNSIGNED`) + an index on it; `BusinessScope` filters by it.
- **FK on-delete**: `cascade` from `businesses`/`locations` downward for full tenant teardown; `restrict` on financial attribution columns (`cashier_user_id`, `location_id` on `sales`) so a sale can never lose its actor; `set null` for optional references (stylist, chair, customer, default staff). Stated per FK below.
- **Indexes**: every FK column indexed; unique constraints + composite indexes called out per table.

---

## 2. Tables

Legend: **PK** primary key · **FK** foreign key (→target, on-delete) · **U** unique · **I** indexed · NN = NOT NULL.

### §businesses
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| name | VARCHAR(255) | NN | | | |
| slug | VARCHAR(255) | NN | | U | auto-gen from name, immutable (BR-BIZ-01) |
| trn | CHAR(15) | NN | | | 15 numeric digits (BR-BIZ-02) |
| country | CHAR(2) | NN | 'AE' | | per-business config |
| currency | CHAR(3) | NN | 'AED' | | per-business config |
| tax_rate | DECIMAL(5,2) | NN | 5.00 | | per-business VAT % |
| invoice_template_settings_json | JSON | NULL | | | logo/footer/address override |
| created_at / updated_at | TIMESTAMP | NULL | | | |

### §locations
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| name | VARCHAR(255) | NN | | | |
| address_json | JSON | NN | | | {street, city, emirate, country} |
| opening_hours_json | JSON | NN | | | 7 days × {open, close} or closed |
| timestamps | | | | | |

### §users  (extends Laravel default users)
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NULL | | FK→businesses (cascade), I | null for super_admin |
| name | VARCHAR(255) | NN | | | |
| email | VARCHAR(255) | NN | | U | always populated (synthetic allowed) |
| username | VARCHAR(255) | NULL | | U | unique when present |
| password | VARCHAR(255) | NN | | | hashed |
| role | VARCHAR(32) | NN | | | enum UserRole (§3) |
| pin_hash | VARCHAR(255) | NULL | | | reserved, not gated v1.0 |
| status | VARCHAR(16) | NN | 'active' | | enum UserStatus (§3) |
| email_verified_at | TIMESTAMP | NULL | | | |
| remember_token | VARCHAR(100) | NULL | | | |
| timestamps | | | | | |

*Location membership is via `§location_user` (many-to-many), not a column. `business_admin` spans all its business's locations implicitly; `location_agent` needs ≥1 membership row; `super_admin` none.*

### §location_user  (pivot — staff ↔ locations, many-to-many)
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| user_id | BIGINT UNSIGNED | NN | | FK→users (cascade), I | |
| location_id | BIGINT UNSIGNED | NN | | FK→locations (cascade), I | |

**U**: `(user_id, location_id)`.

### §audit_logs  (append-only)
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NULL | | I | null for super_admin actions |
| user_id | BIGINT UNSIGNED | NULL | | FK→users (set null), I | actor |
| action | VARCHAR(64) | NN | | | e.g. `sale.close` |
| entity_type | VARCHAR(64) | NN | | I (w/ entity_id) | |
| entity_id | BIGINT UNSIGNED | NULL | | | |
| before_json | JSON | NULL | | | null on create |
| after_json | JSON | NULL | | | null on delete |
| at | TIMESTAMP | NN | | | action time |

*No `updated_at` — rows are immutable (BR-AUDIT-02).*

### §customers
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| mobile_number | VARCHAR(20) | NN | | | normalized E.164 |
| name | VARCHAR(255) | NULL | | | |
| timestamps | | | | | |

**U**: `(business_id, mobile_number)`.

### §services
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| name | VARCHAR(255) | NN | | | EN primary |
| translations | JSON | NULL | | | {"ar": "..."} |
| price | BIGINT UNSIGNED | NN | | | |
| default_commission_pct | DECIMAL(5,2) | NN | 0.00 | | 0–100 |
| duration_minutes | SMALLINT UNSIGNED | NULL | | | informational |
| timestamps | | | | | |

### §combos
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| name | VARCHAR(255) | NN | | | |
| translations | JSON | NULL | | | |
| price | BIGINT UNSIGNED | NN | | | own price, NOT sum |
| combo_commission_pct | DECIMAL(5,2) | NN | 0.00 | | flat |
| default_primary_stylist_user_id | BIGINT UNSIGNED | NULL | | FK→users (set null) | |
| timestamps | | | | | |

### §combo_services  (pivot)
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| combo_id | BIGINT UNSIGNED | NN | | FK→combos (cascade), I | |
| service_id | BIGINT UNSIGNED | NN | | FK→services (cascade) | |
| display_order | SMALLINT UNSIGNED | NN | 0 | | |

**U**: `(combo_id, service_id)`.

### §staff_service_commissions  (schema only v1.0, no UI)
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| user_id | BIGINT UNSIGNED | NN | | FK→users (cascade) | |
| service_id | BIGINT UNSIGNED | NN | | FK→services (cascade) | |
| commission_pct | DECIMAL(5,2) | NN | | | override; absence = inherit default |

**U**: `(user_id, service_id)`.

### §chairs
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| location_id | BIGINT UNSIGNED | NN | | FK→locations (cascade), I | |
| name | VARCHAR(255) | NN | | | |
| is_active | BOOLEAN | NN | 1 | | |
| default_staff_user_id | BIGINT UNSIGNED | NULL | | FK→users (set null), U | one chair per staff (BR-CHAIR-02) |
| timestamps | | | | | |

**U**: `(location_id, name)`, `default_staff_user_id`.

### §inventory_items
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| location_id | BIGINT UNSIGNED | NN | | FK→locations (cascade), I | |
| name | VARCHAR(255) | NN | | | |
| category | VARCHAR(64) | NULL | | | |
| on_hand_qty | INT UNSIGNED | NN | 0 | | |
| in_use_qty | INT UNSIGNED | NN | 0 | | |
| reorder_threshold | INT UNSIGNED | NN | 0 | | low-stock trigger |
| timestamps | | | | | |

*Low-stock is derived (`on_hand_qty ≤ reorder_threshold`), not stored.*

### §invoice_counters
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| series | VARCHAR(16) | NN | 'INV' | | |
| next_value | BIGINT UNSIGNED | NN | 1 | | locked FOR UPDATE at close |
| timestamps | | | | | |

**U**: `(business_id, series)`.

### §sales
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| location_id | BIGINT UNSIGNED | NN | | FK→locations (restrict) | preserve attribution |
| customer_id | BIGINT UNSIGNED | NULL | | FK→customers (set null) | anonymous allowed |
| cashier_user_id | BIGINT UNSIGNED | NN | | FK→users (restrict) | |
| customer_gender | VARCHAR(8) | NN | | | enum CustomerGender (§3) |
| subtotal | BIGINT UNSIGNED | NN | | | |
| discount | BIGINT UNSIGNED | NN | 0 | | |
| discount_type | VARCHAR(8) | NULL | | | enum DiscountType (§3) |
| tax | BIGINT UNSIGNED | NN | | | |
| total | BIGINT UNSIGNED | NN | | | |
| status | VARCHAR(16) | NN | 'closed' | | enum SaleStatus (§3) |
| invoice_number | VARCHAR(32) | NN | | | |
| invoice_series | VARCHAR(16) | NN | 'INV' | | |
| invoice_pdf_path | VARCHAR(255) | NULL | | | set by async job |
| closed_at | TIMESTAMP | NN | | | |
| timestamps | | | | | |

**U**: `(business_id, invoice_series, invoice_number)`. **I (composite)**: `(business_id, location_id, closed_at)`; index `customer_id`.

### §sale_lines
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| sale_id | BIGINT UNSIGNED | NN | | FK→sales (cascade), I | |
| line_type | VARCHAR(8) | NN | | | enum SaleLineType (§3) |
| service_id | BIGINT UNSIGNED | NULL | | FK→services (set null) | when line_type=service |
| combo_id | BIGINT UNSIGNED | NULL | | FK→combos (set null) | when line_type=combo |
| combo_snapshot_json | JSON | NULL | | | frozen combo makeup at close |
| stylist_user_id | BIGINT UNSIGNED | NULL | | FK→users (set null), I | |
| chair_id | BIGINT UNSIGNED | NULL | | FK→chairs (set null), I | auto from stylist default |
| quantity | INT UNSIGNED | NN | 1 | | |
| unit_price | BIGINT UNSIGNED | NN | | | snapshot |
| line_total | BIGINT UNSIGNED | NN | | | quantity × unit |

### §payments
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| sale_id | BIGINT UNSIGNED | NN | | FK→sales (cascade), I | |
| method | VARCHAR(8) | NN | | | enum PaymentMethod (§3) |
| amount | BIGINT UNSIGNED | NN | | | |

### §commission_entries
| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | NN | auto | PK | |
| business_id | BIGINT UNSIGNED | NN | | FK→businesses (cascade), I | |
| sale_line_id | BIGINT UNSIGNED | NN | | FK→sale_lines (cascade), I | |
| user_id | BIGINT UNSIGNED | NN | | FK→users (restrict), I | earner |
| amount | BIGINT UNSIGNED | NN | | | |
| posted_at | TIMESTAMP | NN | | | |
| reversal_of | BIGINT UNSIGNED | NULL | | FK→commission_entries (set null) | v1.1 reversals |

---

## 3. Enum catalog

| Enum | Column(s) | Values |
|---|---|---|
| UserRole | users.role | `super_admin`, `business_admin`, `location_agent` |
| UserStatus | users.status | `active`, `on_leave`, `terminated` |
| CustomerGender | sales.customer_gender | `male`, `female`, `child` |
| DiscountType | sales.discount_type | `percent`, `fixed` |
| SaleStatus | sales.status | `closed` *(reserved: `voided` v1.1)* |
| SaleLineType | sale_lines.line_type | `service`, `combo` *(reserved: `retail_item` v1.1)* |
| PaymentMethod | payments.method | `cash`, `card` |

---

## 4. Business-rules registry

Stable IDs. Tasks cite the ID; each rule should map to ≥1 automated test. Type: **R** rule · **INV** invariant · **V** validation.

### Cross-cutting (was G1–G18)

| ID | Type | Rule |
|---|---|---|
| BR-TEN-01 | R | Every business-owned row has `business_id`; reads/writes auto-scope via BusinessScope; super_admin bypasses. |
| BR-TEN-02 | R | Cross-business access returns **404** (don't leak existence). |
| BR-TEN-03 | R | `location_agent` limited to its **assigned locations** (the set in `location_user`) for writes and to its own sales for reads. `business_admin` spans all its business's locations. |
| BR-AUTH-01 | R | Three roles only: super_admin, business_admin, location_agent. |
| BR-AUTH-02 | R | Role mismatch → 403; unauthenticated → 302 `/login` (web) / 401 (api). |
| BR-AUTH-03 | R | `terminated` users cannot authenticate by any path. |
| BR-MONEY-01 | R | All money is integer fils in BIGINT UNSIGNED; convert only at edges. |
| BR-MONEY-02 | R | Rounding HALF_UP, applied only at sale-close totals — never on input. |
| BR-MONEY-03 | INV | No float math on money anywhere. |
| BR-TIME-01 | R | App timezone Asia/Dubai; report "today"/day boundaries in Dubai local; store UTC. |
| BR-AUDIT-01 | R | Every state-changing domain action writes one `audit_logs` row. |
| BR-AUDIT-02 | INV | `audit_logs` rows are append-only (never updated/deleted by app). |
| BR-VAL-01 | R | Validation lives in UseCase Request; invalid input → 422 (api)/inline (web). |
| BR-VAL-02 | R | Business-rule violation → 409 (api)/actionable message (web). |
| BR-VAL-03 | R | Reject unknown fields; re-check scope on client-sent IDs (BR-TEN-02). |
| BR-STATE-01 | R | Closed sale is immutable (no edit/delete/re-close/line/payment change) → 409. |
| BR-STATE-02 | R | Only documented state transitions allowed; illegal → 409. |
| BR-CONC-01 | R | Invoice numbering via SELECT … FOR UPDATE; gapless+sequential per (business, series); counter never decrements. |
| BR-CONC-02 | R | Money/stock-mutating actions are transactional; no partial writes persist. |
| BR-IDEM-01 | R | Onboarding + sale-close accept an idempotency key; replay returns the original result, no double-create. |

### Business & location

| ID | Type | Rule |
|---|---|---|
| BR-BIZ-01 | R | `slug` generated from name on create; immutable after. |
| BR-BIZ-02 | V | `trn` = exactly 15 numeric digits. |
| BR-BIZ-03 | R | `country`/`currency`/`tax_rate` are per-business config; all calc reads them (never hardcoded). |
| BR-BIZ-04 | INV | `slug` globally unique; collision retries with numeric suffix. |
| BR-BIZ-05 | R | TRN is NOT unique across businesses (duplicates allowed). |
| BR-LOC-01 | V | `address_json` = {street, city, emirate, country}. |
| BR-LOC-02 | V | `opening_hours_json` = 7 days; each open day has `open < close`. |
| BR-LOC-03 | INV | Location `country` mirrors its business country. |

### Staff

| ID | Type | Rule |
|---|---|---|
| BR-STAFF-01 | V | At least one of email/username required. |
| BR-STAFF-02 | R | Username-only → synthetic email `<username>@<slug>.saloonify.local` (lowercased/slugified). |
| BR-STAFF-03 | R | business_admin may create only business_admin/location_agent (never super_admin). |
| BR-STAFF-04 | V | location_agent must be assigned to ≥1 location (via `location_user`), all belonging to the caller's business. business_admin needs no membership rows (spans all). |
| BR-STAFF-05 | R | Status machine: active↔on_leave; active/on_leave→terminated; terminated terminal (no reactivation). |
| BR-STAFF-06 | R | Cannot terminate the last active business_admin of a business. |
| BR-STAFF-07 | R | Cannot change a user's business_id. |
| BR-STAFF-08 | R | on_leave users can still log in; only terminated blocked. |
| BR-STAFF-09 | R | business_admin spans all its business's locations (no `location_user` rows); a location_agent must keep ≥1 membership. |

### Chairs

| ID | Type | Rule |
|---|---|---|
| BR-CHAIR-01 | R | Chair's location must belong to caller's business. |
| BR-CHAIR-02 | INV | A staff is default of at most one chair (`default_staff_user_id` unique). |
| BR-CHAIR-03 | V | Default staff must be active, same business, and a **member of the chair's location** (`location_user`). |
| BR-CHAIR-04 | R | Inactive chair cannot be assigned to a new sale line. |
| BR-CHAIR-05 | V | Chair name unique within a location. |

### Customers

| ID | Type | Rule |
|---|---|---|
| BR-CUST-01 | R | Identified by mobile; business-scoped (same mobile in 2 businesses = 2 rows); no cross-business linking. |
| BR-CUST-02 | V | Mobile parses to valid E.164 (UAE default region); stored normalized. |
| BR-CUST-03 | R | Find-or-create is idempotent on the normalized number. |

### Services & combos

| ID | Type | Rule |
|---|---|---|
| BR-SVC-01 | V | price ≥ 0; commission 0–100; duration ≥ 0 if set. |
| BR-SVC-02 | R | Editing a service price affects only future sales (past lines snapshot). |
| BR-SVC-03 | R | Deleting a service used by a combo is blocked → 409. |
| BR-SVC-04 | INV | AED↔fils conversion exact + reversible (×100/÷100 integer). |
| BR-COMBO-01 | V | Combo references ≥1 service, all same business. |
| BR-COMBO-02 | R | Combo has its own price + flat commission % (not sum of parts). |
| BR-COMBO-03 | V | default_primary_stylist (if set) active + same business. |
| BR-COMBO-04 | R | Editing a combo never mutates past sales (snapshot at close). |

### Inventory

| ID | Type | Rule |
|---|---|---|
| BR-INV-01 | R | Item belongs to a location of caller's business. |
| BR-INV-02 | R | Actions: receive(+) → on_hand; mark in-use moves on_hand→in_use; mark finished reduces in_use. Manual only, no sale linkage. |
| BR-INV-03 | INV | on_hand_qty ≥ 0 and in_use_qty ≥ 0 always. |
| BR-INV-04 | R | Cannot move more to in-use than on_hand → 409. |
| BR-INV-05 | R | Cannot finish more than in_use → 409. |
| BR-INV-06 | R | Low-stock (on_hand ≤ threshold) is derived, surfaced in-app only (no email/SMS). |

### Sale, payment, commission

| ID | Type | Rule |
|---|---|---|
| BR-SALE-01 | R | Close is a single transaction; any failure rolls back everything. |
| BR-SALE-02 | R | Invoice number gapless + sequential per (business, series) via FOR UPDATE. |
| BR-SALE-03 | V | Σ payments == total (exact); methods cash/card only. |
| BR-SALE-04 | V | customer_gender required; ≥1 line required. |
| BR-SALE-05 | R | Each line snapshots unit_price; combo lines snapshot combo_snapshot_json. |
| BR-SALE-06 | R | chair_id persisted per line (auto from stylist default, overridable; active chairs only). |
| BR-SALE-07 | R | Sale rung at one location the operator belongs to. Each line's stylist must be a **member of the sale's location** and active; chair must belong to the sale's location and be active. Re-validated at close → 409 if not. |
| BR-SALE-10 | R | Operator may transact the sale's location: business_admin = any location of the business; location_agent = a location in its `location_user` set. Else 403/409. |
| BR-SALE-08 | INV | Created directly in `closed`; immutable thereafter (BR-STATE-01). |
| BR-SALE-09 | R | total = subtotal − discount + tax; VAT = round(net × tax_rate) HALF_UP. |
| BR-PAY-01 | V | amount > 0 per payment row. |
| BR-COMM-01 | R | One commission entry per earning line at close. |
| BR-COMM-02 | R | Service line → stylist earns service % of post-discount line basis. |
| BR-COMM-03 | R | Combo line → flat combo % to default primary stylist. |
| BR-COMM-04 | INV | Σ commission ≤ Σ line totals. |

### Receipt, reports, PWA

| ID | Type | Rule |
|---|---|---|
| BR-PDF-01 | R | Invoice PDF carries FTA minimum fields + TRN; amounts from stored fils. |
| BR-PDF-02 | R | PDF served only via signed, expiring URL; expired/tampered → 403. |
| BR-PDF-03 | R | Render job idempotent per sale; PDF is async, non-blocking to close. |
| BR-RPT-01 | INV | Report totals == raw SQL aggregate exactly (fils, no drift). |
| BR-RPT-02 | R | Default range = today in Asia/Dubai; location_agent sees only own location/sales. |
| BR-RPT-03 | R | Report includes per-stylist commission, per-chair utilization, inventory on-hand + low-stock. |
| BR-PWA-01 | R | Online-only: offline shows banner, preserves cart, disables close; reconnect re-validates (BR-SALE-07). |
| BR-PWA-02 | R | Service worker caches static assets only — no dynamic-route runtime cache. |

---

## 5. How tasks reference this doc

- A schema task says: *"Migration: `chairs` per `data-model.md §chairs`."* — no column list duplicated in the task.
- A rule/acceptance says: *"Enforces BR-SALE-02, BR-SALE-03; test each."*
- When a column or rule changes, edit it **here once**; tasks stay correct by reference.
