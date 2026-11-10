# Production deployment (CRM + Booking + Donation + Home)

## No separate data migration

Schema updates in this release are **additive only** (new nullable columns / new tables). Existing production data is **not** rewritten.

| Change | How it applies on deploy |
|--------|-------------------------|
| `subscription_invoices.gateway_order_id` | CRM `migrate_schema()` → `ensure_column` on first CRM request or `php crm/bin/preflight_deploy.php` |
| `bookings.erp_receipt_emailed_at` | Booking `ErpJournalSync::ensureLinkSchema()` on first paid booking sync (MySQL only) |
| `online_donation_intents` table | Created on first visit to `Booking/donation/` (`schema.php`) |

You do **not** need to run a one-off data migration script. Hit each app once after upload (or run preflight).

## Pre-flight (on server or AMPPS before go-live)

```bash
php crm/bin/preflight_deploy.php
php crm/tests/subscription_e2e_test.php
php crm/tests/payments_receipts_readiness_test.php
```

Optional cron after deploy:

```bash
php crm/bin/subscription_due.php   # due invoices + email
```

## Environment (production)

| File | Must have |
|------|-----------|
| `crm/.env` | `APP_URL=https://sjtbengaluru.org/crm`, DB credentials, **do not** upload repo-root `.env` |
| `Booking/.env` | `APP_URL=…/Booking`, `ERP_APP_URL=…/crm`, `PAYMENT_GATEWAY=razorpay`, live `RAZORPAY_*`, `GATEWAY_SSL_VERIFY=true`, `APP_ENV=production` |
| `home/index.html` | `window.SJT_DONATION_URL = 'https://sjtbengaluru.org/Booking/donation/'` (and booking/live URLs as today) |

SMTP: CRM **Settings › Messages** (receipts, subscription payment requests, donation receipts).

## Manual smoke (15 min)

1. **Home** — Donate nav → `Booking/donation/` form loads; Online Booking / Live Darshan links OK.
2. **Donation** — test Razorpay amount → thanks → view receipt link → `…/crm/index.php?r=receipts/open/…`; email if configured.
3. **Booking** — small seva booking → pay → invoice email + ERP receipt email (if email on booking).
4. **CRM Subscriptions** — open page (auto due invoices if any) → pay link → Razorpay → Paid → donation in **Donations** → one receipt; no second invoice for same month after paid.
5. **CRM Receipt** — public link opens without login.

## Automated tests run (latest)

- CRM: 27+ `tests/*_test.php` (2 seed-dependent failures in `receipt_share_test` / `install_check_test` on dev DB — not deploy blockers).
- Subscription: `subscription_e2e_test.php`, `subscription_receipt_test.php`, `subscriber_test.php` — **passed**.
- Payments/receipts: `payments_receipts_readiness_test.php` — **passed**.

Full Booking HTTP suite (optional, long):

```bash
cd Booking && php tests/run.php
```

## Upload checklist

- [ ] `crm/`, `Booking/`, `Booking/donation/`, `home/`
- [ ] `crm/storage/` writable (receipts, sessions, uploads)
- [ ] `Booking/storage/` writable (logs)
- [ ] PHP **8.1+**, extensions: pdo_mysql, curl, mbstring, gd (optional)
- [ ] Razorpay live keys; webhook optional
- [ ] Remove or never upload repo-root `.env` (local overrides only)
