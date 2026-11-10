# Online payments & receipts (production)

## What sends what

| Flow | Payment | ERP receipt PDF | Public receipt link in email |
|------|---------|-----------------|------------------------------|
| **Booking** (seva) | Razorpay in Booking | Yes, after ledger sync | Yes — `crm/bin/send_donation_receipt_email.php` (once per booking; invoice email is separate) |
| **Online donation** (`Booking/donation/`) | Razorpay | Yes | Yes — same ERP `send_donation_receipt()` |
| **Subscription** (`crm` pay link) | Razorpay via CRM | Yes | Yes — `send_subscription_receipt()` → `send_donation_receipt()` |

Public link format (production):

`https://sjtbengaluru.org/crm/index.php?r=receipts/open/{32-char-token}`

## Environment

- `crm/.env`: `APP_URL=https://sjtbengaluru.org/crm`, SMTP under Settings → Messages.
- `Booking/.env`: `PAYMENT_GATEWAY=razorpay`, `RAZORPAY_*`, `ERP_APP_URL=https://sjtbengaluru.org/crm`.
- Local AMPPS: repo-root `.env` may set `GATEWAY_SSL_VERIFY=false` and `APP_ENV=staging` (never upload repo-root `.env`).

## Subscriptions (due invoices)

On each visit to **Subscriptions** (GET), the CRM marks past-due rows **Overdue** and, for each **Active** subscriber who has no invoice yet for this month and has not paid this period, creates one invoice and runs `notify_invoice` (SMS log + email when SMTP is set). WhatsApp is still manual via the row button.

Daily/cron (optional):

```bash
php crm/bin/subscription_due.php
```

Rules: one invoice per period; no new invoice if paid or unpaid invoice already exists; payment posts one **donations** row; receipt is issued once; duplicate gateway payment is ignored.

## Smoke tests (CLI)

```bash
php crm/tests/subscription_e2e_test.php
php crm/tests/payments_receipts_readiness_test.php
php crm/tests/subscription_receipt_test.php
php crm/bin/test_subscription_gateway.php
php Booking/donation/bin/check_receipt_url.php aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
```

## Manual checklist

1. Subscription: open email/WhatsApp pay link → Pay now → Razorpay test card → success page + receipt link + inbox PDF.
2. Donation: `Booking/donation/` → pay → thanks page View receipt + email.
3. Booking: pay online → payment email (invoice) + second email with temple receipt PDF and CRM link (if devotee email on booking).
