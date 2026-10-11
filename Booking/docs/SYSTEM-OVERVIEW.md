# Shree Jagannatha Temple Bengaluru — Online Booking System

Online booking for pujas, ceremonies and food / prasadam coupons, with UPI QR payments,
PDF invoices, WhatsApp notifications and an administrator dashboard.
Built for **https://sjtbengaluru.org/Booking** on the temple's existing cPanel hosting.

## What was found on the existing site (10 Oct 2026)

| Checked | Finding |
|---|---|
| https://sjtbengaluru.org/ | cPanel default **"Coming Soon"** placeholder page. No CMS, theme or application is installed yet. |
| https://sjtbengaluru.org/Booking | 404 — nothing there. |
| https://shreejagannathtemplesarjapura.org/#services | Page is rendered by JavaScript; no UPI ID or payment details are visible to a crawler. Its QR approach was used as the model: QR + amount + booking reference, then manual reconciliation. |

So there is nothing to reuse or break. The app is plain **PHP 8.1+ and MySQL/MariaDB**,
which every cPanel host supports, needs no Node.js, no background service and no SSH
(SSH just makes setup easier). It lives in its own folder and only answers under `/Booking`.

## Features

- Service cards for Special Puja, Vehicle Puja, Shraddha Karma, Brata Ghara / Upanayana,
  Bhaghar / Marriage, Other Temple Services and **Food-Only Booking**. Admins add, edit,
  price, enable/disable services and their extra questions without code.
- Multiple services in one booking (configurable per service), date + time-slot choice with
  live remaining places, participants, service-specific details, special instructions.
- **Food / Prasadam coupons**: ₹150 and ₹250 categories (configurable, more can be added),
  separate quantity per category, itemised on summary and invoice. Food-only bookings work
  on their own with a mandatory coupon choice.
- Server-side pricing only (the browser shows an estimate; the server recalculates at review
  and again at submit and warns if prices changed).
- Booking numbers like **SJT-2026-000123** and invoice numbers like **SJTB/2026-27/000123**
  (Indian financial year), generated in a database transaction — unique even under
  simultaneous submissions.
- **Payment**: dynamic UPI QR per booking (exact amount + booking number pre-filled), or the
  temple's own static QR; download / full-screen QR; "Pay with UPI app" link on phones;
  UTR capture. Statuses: Pending, Awaiting verification, Paid, Failed, Refunded.
  **A booking is never marked paid because a devotee typed a UTR** — an admin verifies against
  the bank statement (or a gateway signature verifies it, if Razorpay is enabled).
- Clear distinction everywhere between *booking request* and *confirmed booking*; ceremonies
  that need approval stay unconfirmed until both payment and approval are done.
- Printable invoice page + PDF (with ₹ symbol, status stamp and pay-QR while unpaid).
- **WhatsApp Cloud API** notifications to devotee and administrators (new booking, payment
  submitted, verified/rejected, approved, rejected, cancelled, rescheduled, charges changed,
  refunded). Every message is logged; failures never block a booking and are shown in the
  dashboard with retry / "mark handled". Delivery receipts via signed webhook.
- **Admin dashboard**: search/filter bookings, full booking view, verify/reject payments,
  approve/reject/cancel/reschedule/complete, set charges for "on request" services, refunds,
  internal notes, availability calendar (closed dates, slot usage), prices, coupons, slots,
  settings, admin users (senior/staff roles), reports (daily/weekly/monthly), CSV exports,
  notification log, and a complete **audit trail** of who changed what and when.
- Security: prepared statements, CSRF tokens, hardened sessions, Argon2id passwords, account
  lockout, rate limits, CSP and other security headers, private capability links for
  booking pages, HMAC-verified webhooks, secrets only in `.env`, HTTPS enforced.
- Elderly-friendly UI: 18px+ text, 48–56px buttons, one step at a time with a progress bar,
  error summary linking to each field, works without JavaScript.

## Folder layout

```
app/            PHP source (Controllers, Services, Repositories, Views, Lib)
bin/            console.php (migrate, create-admin, check, cron jobs), backup.sh, restore.sh
migrations/     database schema + default data (applied once, never destructive)
assets/         css, js, images (served directly)
storage/        logs, uploads, sessions, cache, backups (not web-visible)
tests/          run.php (60 end-to-end tests), ui_test.py (browser tests), mock WhatsApp API
docs/           deployment, configuration, WhatsApp templates, backup, testing, test results
```

The whole `booking/` folder deploys to a single web folder (e.g. `public_html/Booking`).
There are **no third-party dependencies**: PDFs are drawn by `app/Lib/PdfDocument.php` and QR
codes by `app/Lib/qrcode.php`, both shared with the ERP `php/` app, and `app/autoload.php`
provides a minimal PSR-4 autoloader instead of Composer.

## Documentation

| Document | For |
|---|---|
| [docs/TEMPLE-ACTION-ITEMS.md](docs/TEMPLE-ACTION-ITEMS.md) | **What the temple must provide or approve before going live** |
| [docs/DEPLOYMENT-CPANEL.md](docs/DEPLOYMENT-CPANEL.md) | Installing staging and production on the cPanel host |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | UPI QR, WhatsApp, admin numbers, prices, slots, availability |
| [docs/WHATSAPP-TEMPLATES.md](docs/WHATSAPP-TEMPLATES.md) | Exact message templates to submit to Meta |
| [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) | Daily work for temple office staff |
| [docs/BACKUP-RECOVERY.md](docs/BACKUP-RECOVERY.md) | Backups, restore, disaster recovery |
| [docs/TESTING.md](docs/TESTING.md) | Test checklist and how to run the tests |
| docs/TEST-RESULTS-*.md | Latest automated results (SQLite, MySQL protocol, browser) |

## Quick start on a computer (development)

```bash
cp .env.example .env            # set DB_DRIVER=sqlite, APP_URL=http://127.0.0.1:8088/Booking, APP_ENV=staging
php bin/console.php migrate
php bin/console.php create-admin admin "Temple Admin" super
php -S 127.0.0.1:8088 index.php
# open http://127.0.0.1:8088/Booking  and  http://127.0.0.1:8088/Booking/admin
php tests/run.php               # full test suite
```
