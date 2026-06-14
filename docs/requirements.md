# Saloonify — v1 Requirements

**Status**: LOCKED (2026-05-01 → 2026-05-23)
**Market**: UAE only
**Currency**: AED
**Tax**: UAE VAT 5% single-rate

---

## 1. Overview

Saloonify is a multi-tenant SaaS for salon / beauty parlor management. v1 ships walk-in POS, inventory, commission, refunds, EOD close, bilingual receipts, and per-location reporting for VAT-registered UAE salons.

- **Onboarding**: no public signup. Super-admin onboards businesses manually.
- **Tenancy**: business → locations → chairs / inventory / staff / customers.
- **Single-currency, single-country per business**: no cross-currency.
- **Customer model**: light CRM keyed by mobile number; business-scoped (no cross-business linking).

---

## 2. Roles & permissions

Fixed enum, hand-rolled, enforced via Laravel Gate/policies. No custom roles in v1.

| Role             | Scope                            | Notes                                                              |
| ---------------- | -------------------------------- | ------------------------------------------------------------------ |
| `super_admin`    | Cross-business                   | Onboards tenants. Saloonify staff only.                            |
| `business_admin` | One business, all locations      | Full access within own business.                                   |
| `location_admin` | One location                     | Acts as manager for PIN gates (refunds, reprints > 30 days).       |
| `agent`          | One location, own sales only     | Stylist / cashier. PWA UI.                                         |

There is no separate `cashier` role. Any location-scoped user acts as cashier; attribution flows via `cashier_user_id` on the sale.

---

## 3. Tenancy & isolation

- Every business-scoped row carries `business_id`.
- `BusinessScope` Eloquent global scope auto-applied via middleware that sets tenant context from authenticated user.
- MySQL has no RLS — isolation is enforced at app layer. Migrations + tests must assert scope.
- Customer scope = business-scoped. Same mobile across two businesses = two distinct `Customer` rows.

---

## 4. Functional modules

### 4.1 Business + location onboarding

- TRN **mandatory** at business creation. 15 numeric digits, validated.
- `Location.address_json` (street, city, emirate, country).
- `Location.opening_hours_json` (per-day open/close).
- `Business.invoice_template_settings_json` (logo, footer text, optional address override).

### 4.2 Customer (light CRM)

- Identified by mobile number, validated via `giggsey/libphonenumber-for-php` (UAE default region).
- Name, mobile, email (optional), notes.
- Gender is **not** stored on the customer. Gender is captured at sale time.

### 4.3 Services & combos

- `Service`: `name_en`, `name_ar`, `price`, `default_commission_pct`, `duration_minutes` (informational).
- `Combo`: own price (**not** auto-sum of constituent services), `combo_commission_pct` flat, single primary stylist v1.
- `ComboService` links combo → constituent services for receipt detail.
- Combo composition is **frozen at sale time** — snapshot stored on `SaleLine` so later edits to the combo don't mutate past sales.
- Multi-stylist commission split = v2.

### 4.4 Inventory

- Unit-count only. No per-mL / per-g consumption tracking.
- `InventoryItem`: name, SKU, tag (`retail` | `back_bar`), reorder threshold.
- `StockMovement` records: type (`received` | `taken`), quantity, by user, when, `expiry_date` (per-movement / per-batch for FIFO reporting), reference (sale ID for `taken`).
- Retail items sold via `SaleLine.line_type=retail_item`. No `is_retail` flag on `Service`.
- Expiry alerts: **in-app only** (no email/SMS v1).

### 4.5 Sales / POS (walk-in only)

- Walk-in only. No appointments / booking v1.
- Cart of `SaleLine`s: service, combo, or retail item.
- Sale-level discount only (percentage **or** fixed amount).
- VAT 5% applied at sale level.
- Customer gender captured on sale: `male` | `female` | `child`.
- Payment methods: cash + card. Method recorded per `Payment` row; sale may have multiple payments.
- `cashier_user_id` always set on sale.
- `stylist_user_id` nullable on line (retail-only lines have no stylist).
- Sale-close = single DB transaction including `SELECT … FOR UPDATE` on `InvoiceCounter` for gapless sequential invoice number.

### 4.6 Refunds & voids

- **Same-day void**: full reversal. Auto-reverses inventory movements and commission entries. No operator prompt.
- **Refund**: requires manager PIN (location-admin or business-admin). Partial allowed. Refund settles in **one** payment method (no partial cross-method v1). Operator chooses method at refund time, recorded on credit note.
- **Credit note**: negative tax invoice with its own sequential numbering series.
- **Reason**: free-text field. No taxonomy v1.
- **Locked period**: void/refund into a closed accounting month posts offsetting entries in the next open month — never blocks the action.

### 4.7 Commission

- Default: `Service.default_commission_pct`. Stylist who delivered the service earns that % of line price (post-discount basis = §10 default 5).
- Override: `StaffServiceCommission` row exists only when an override applies. NULL / missing = inherit default.
- Combo: single primary stylist + flat `combo_commission_pct`. Multi-stylist split deferred to v2.
- Void/refund commission reversal is automatic.

### 4.8 EOD cash close

- **Per-location only** (not per-cashier).
- System totals: expected cash drawer = opening float + cash payments − cash refunds.
- Operator enters counted cash. Variance computed.
- No hard variance cap. Manager confirmation is always sufficient to close.

### 4.9 Reporting

- Totals (sales, refunds, tax, commission liability).
- Per-location breakdowns.
- Inventory: on-hand, FIFO expiry upcoming.
- Stylist commission summary by date range.

### 4.10 Receipt printing

- Thermal ESC/POS via `mike42/escpos-php`. Server renders PNG raster.
- Target printers: Epson TM-T82, Xprinter XP-58 (USB or LAN).
- Default template **bilingual EN+AR** (services pull `name_en` + `name_ar`).
- FTA tax invoice format: business name, TRN, invoice number, date, line items, subtotal, VAT 5%, total.
- **Reprint**:
  - Within 30 days: open to location-admin (no PIN gate).
  - Older than 30 days: still location-admin (just no PIN gate either).

---

## 5. Data model

Money precision: `decimal(12,3)` line-level, `decimal(12,2)` payment + displayed totals. Rounding: HALF_UP via `moneyphp/money` for FTA compliance.

| Entity                    | Key fields                                                                                                                  |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `Business`                | `id`, `name`, `trn` (15 digits), `invoice_template_settings_json`, timestamps                                               |
| `Location`                | `id`, `business_id`, `name`, `address_json`, `opening_hours_json`                                                           |
| `User`                    | `id`, `business_id` (nullable for super_admin), `location_id` (nullable), `role`, `name`, `email`, `password_hash`, `pin_hash`, `status` (`active`/`on_leave`/`terminated`) |
| `Customer`                | `id`, `business_id`, `mobile_e164`, `name`, `email`, `notes`                                                                |
| `Service`                 | `id`, `business_id`, `name_en`, `name_ar`, `price`, `default_commission_pct`, `duration_minutes`                            |
| `Combo`                   | `id`, `business_id`, `name_en`, `name_ar`, `price`, `combo_commission_pct`                                                  |
| `ComboService`            | `combo_id`, `service_id`, `display_order`                                                                                   |
| `StaffServiceCommission`  | `user_id`, `service_id`, `commission_pct` (row exists only on override)                                                     |
| `InventoryItem`           | `id`, `business_id`, `location_id`, `sku`, `name`, `tag` (`retail`/`back_bar`), `reorder_threshold`, `unit_price` (retail) |
| `StockMovement`           | `id`, `inventory_item_id`, `type` (`received`/`taken`), `quantity`, `user_id`, `expiry_date`, `reference_type`, `reference_id` |
| `Sale`                    | `id`, `business_id`, `location_id`, `customer_id`, `cashier_user_id`, `customer_gender`, `subtotal`, `discount_amount`, `discount_type`, `tax_amount`, `total`, `invoice_number`, `invoice_series`, `status`, `closed_at` |
| `SaleLine`                | `id`, `sale_id`, `line_type` (`service`/`combo`/`retail_item`), `service_id`, `combo_id`, `inventory_item_id`, `combo_snapshot_json`, `stylist_user_id` (nullable), `quantity`, `unit_price`, `line_total` |
| `Payment`                 | `id`, `sale_id`, `method` (`cash`/`card`), `amount`                                                                         |
| `Refund`                  | `id`, `sale_id`, `manager_user_id`, `payment_method` (`cash`/`card`), `amount`, `reason`, `credit_note_id`                  |
| `CreditNote`              | `id`, `business_id`, `refund_id`, `credit_note_number`, `credit_note_series`                                                |
| `InvoiceCounter`          | `id`, `business_id`, `series`, `next_value` (FOR UPDATE locked at sale-close)                                               |
| `EodCashClose`            | `id`, `location_id`, `business_date`, `opening_float`, `expected_cash`, `counted_cash`, `variance`, `manager_user_id`, `notes` |
| `CommissionEntry`         | `id`, `business_id`, `sale_line_id`, `user_id`, `amount`, `posted_at`, `reversal_of` (nullable)                             |
| `AuditLog`                | `id`, `business_id`, `user_id`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `at`                     |

JSON columns (MySQL 8 native): `Location.address_json`, `Location.opening_hours_json`, `Business.invoice_template_settings_json`, `SaleLine.combo_snapshot_json`.

PII encryption-at-rest: any future Emirates ID / IBAN fields via Laravel `Crypt` with per-business KEK derived from a KMS data key.

---

## 6. Compliance (UAE)

- **VAT 5%**, single rate, applied at sale level. Tax breakdown line on invoice.
- **TRN mandatory** at business onboarding (15 numeric digits). v1 targets VAT-registered salons only. Below-threshold businesses wait for v1.1.
- **FTA tax invoice format**: business name, address, TRN, invoice number, date, customer name (if captured), line items with `name_en` + `name_ar`, subtotal, VAT, total.
- **Sequential numbering**: `SELECT … FOR UPDATE` on `invoice_counters` row inside the sale-close transaction. Per-business, per-series. Gapless guaranteed.

---

## 7. Non-functional

- **Stack** (locked separately — see memory `project_saloonify_stack.md`): Laravel 13 + PHP 8.4 + MySQL 8 + Inertia.js + Vue 3 + Redis + Horizon.
- **Agent UI**: PWA, **online-only**. Offline = block-and-banner; cart preserved in IndexedDB; sale-close disabled until reconnect.
- **Languages**: EN + AR. Receipts bilingual by default. UI language selection TBD pre-build.
- **Expiry alerts**: in-app only.
- **Hosting**: AWS me-central-1. Laravel Forge on EC2 + RDS MySQL sufficient for v1.
- **Error tracking**: Sentry. **APM**: Sentry Performance or Laravel Pulse.
- **CI/CD**: GitHub Actions. Forward-only migrations. Staging on main merge, prod via manual approval.

---

## 8. Out of scope v1 (explicit reject list)

Reject during scoping. Do not implement under any framing.

- Appointments / booking
- Tipping
- Prepaid packages
- Sale on credit / khaata
- Offline-first PWA (online-only is shipped instead)
- Multi-currency
- Multi-tax
- Multi-stylist combo commission split
- Refund reason taxonomy
- Custom roles
- Native mobile app
- Cross-business customer linking
- Per-cashier EOD close
- Email / SMS expiry alerts
- Public business signup

Expert flagged risk that prepaid packages cover 25–40% of UAE salon market — user accepts this risk consciously to ship v1.

---

## 9. (reserved for future use)

---

## 10. Locked defaults

All items in §10.1, §10.2, §10.3 marked **LOCKED 2026-05-01**.

### 10.1 Story-level (LOCKED)

1. EOD cash close = per-location only (no per-cashier).
2. Drawer variance = no hard cap; manager confirmation always sufficient.
3. Refund payment route = operator-chosen at refund time, recorded on credit note.
4. Commission void/refund into locked period = post offsetting entries in next open month (don't block the void/refund itself).
5. Discount scope = sale-level only.
6. Combo composition = frozen at sale time (snapshot on `SaleLine`).
7. Customer gender values = `male` / `female` / `child`.
8. Staff offboarding = combined into status enum: `active` / `on_leave` / `terminated`.
9. Expiry alerts = in-app only (no email/SMS push v1).
10. PWA disconnection = block-and-banner; cart preserved in IndexedDB; sale-close disabled offline.
11. Reprint = open to location-admin within 30 days; older requires location-admin too (just no PIN gate).
12. Inventory at void = auto-reverse, no operator prompt.

### 10.2 Data-model (LOCKED)

1. `StaffServiceCommission` row only when override exists; NULL/missing = inherit `Service.default_commission_pct`.
2. Combo commission = single primary stylist + flat `combo_commission_pct` (multi-stylist split = v2).
3. Money precision = `decimal(12,3)` line-level, `decimal(12,2)` payment + displayed totals.
4. Customer scope = business-scoped; no cross-business linking v1.
5. Opening hours = JSON column on `Location` (not separate table).
6. `StockMovement.expiry_date` = per-movement (per-batch) for FIFO expiry reporting.
7. No `is_retail` flag on `Service`. Retail = `InventoryItem.tag=retail` + `SaleLine.line_type=retail_item`.
8. Refunds don't need partial-cross-method v1 (refund all in one method).
9. Retail-only sale lines = `stylist_user_id` nullable.
10. No separate `cashier` role; any location-scoped role acts as cashier (attribution via `cashier_user_id` only).

### 10.3 Compliance (LOCKED)

- TRN stays **mandatory** at business onboarding (15 numeric digits validated). Saloonify v1 targets VAT-registered salons only. Below-threshold businesses → v1.1.

---

## 11. Open items (carry-forward to stack / pre-design phase)

These were marked open in the round-3 decision set and remain to be resolved before schema/API contracts are frozen:

- **Q-001** — TBD
- **Q-005** — TBD
- **Q-010** — TBD
- **Q-011** — TBD
- **Q-039** — TBD
- **Q-040** — TBD
- **Q-042** — TBD

(Question text was in the original `requirements.md`, which was lost; will be re-resolved on first reference during build planning.)

---

## Sources

This document reconstitutes locked decisions from auto-memory:

- `project_saloonify_overview.md`
- `project_saloonify_v1_scope.md`
- `project_saloonify_v1_decisions_round2.md`
- `project_saloonify_v1_decisions_round3.md`
- `project_saloonify_stack.md` (stack only — §7)
