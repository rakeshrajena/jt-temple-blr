# Online donation (`Booking/donation/`)

Standalone module for **custom-amount temple donations** (up to **₹10,000** per transaction). Uses the same MySQL database (`donors`, `donations`), Razorpay keys, and `.env` as the main Booking app. No changes to Booking routes or controllers are required.

## URL

- Local: `http://localhost:8080/Booking/donation/`
- Production: `https://sjtbengaluru.org/Booking/donation/`

## Behaviour

1. Devotee enters name, mobile, optional email/PAN, purpose, and amount (₹1–₹10,000).
2. Amounts above ₹10,000 are rejected with a message to contact temple management for offline payment.
3. Razorpay Checkout collects payment (same gateway as bookings).
4. On success, a row is written to ERP `donations` / `donors` with `online_booking_no` = `ODN-…` reference; the ERP CLI generates the receipt PDF and share link. The thank-you page shows **View / Download PDF**; if email was entered, the receipt is sent with PDF attached (same SMTP as CRM).

Optional: set `PHP_CLI_PATH` in `.env` on Windows if receipt generation or email fails (path to `php.exe`).

## Schema

On first request the module creates `online_donation_intents` (payment state only). ERP tables already exist in `crm/schema.sql`.

## Configuration

Uses `Booking/.env` (and repo root `.env` overrides): `PAYMENT_GATEWAY`, `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, database settings.

## Home page link

Set `window.SJT_DONATION_URL` in `home/index.html`; `home/site-links.js` wires the Donate section and navigation.
