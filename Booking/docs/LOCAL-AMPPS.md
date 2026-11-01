# Local development — AMPPS (Windows)

## URLs and ports

| Service | Typical AMPPS setting |
|---------|------------------------|
| **Website** | `http://localhost:8080` (if you changed Apache from port 80) |
| **This app** | `http://localhost:8080/jt_blr/jt-temple-blr/Booking` |
| **MySQL** | Host **`localhost`**, port **`3306`** (not 8080 — 8080 is the web server) |

`APP_URL` in `.env` must match the browser address **exactly** (scheme, host, port, and path). The app uses it for links, CSRF, and Razorpay return URLs.

## `.env` (local)

```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=http://localhost:8080/jt_blr/jt-temple-blr/Booking

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=sjt_temple_blr
DB_USER=root
DB_PASS=mysql
```

Default AMPPS MySQL password is `mysql`. If login fails, open **AMPPS → MySQL settings** and reset the root password, then update `DB_PASS`.

Use the **same** `DB_NAME` as the ERP (`php/.env`) if you want one shared local database.

## Setup commands

From the `Booking` folder in PowerShell:

```powershell
cd "C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking"
php bin\console.php migrate
php bin\console.php create-admin admin "Local Admin" super
```

Or once: `http://localhost:8080/jt_blr/jt-temple-blr/Booking/install.php` (requires `INSTALL_TOKEN` in `.env`).

## Razorpay (test mode)

In `.env`:

```env
PAYMENT_GATEWAY=razorpay
BOOKING_UPI_MANUAL=false
RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
```

Paste **test** keys from [Razorpay dashboard](https://dashboard.razorpay.com/). Devotees only see **Pay now** (no UPI QR / UTR form).

Webhooks to localhost need a tunnel (e.g. ngrok); for local testing, **Pay now** + server verify after checkout is usually enough.

### Booking admin login (single sign-on with ERP)

When ERP and Booking share the database (`donors` + `bookings` tables), **one login covers both**:

1. Sign in at **ERP** (`/php/`).
2. Open **Temple › Online bookings** or `/Booking/admin` — no second login.

Both apps share the PHP session cookie (`jt_blr_session`) under the project folder path. Staff accounts live in the **`users`** table (migration `005_shared_staff_users`).

If SSO misbehaves after an update, sign out of ERP once and sign in again. Set `STAFF_SSO=false` in Booking `.env` to bring back the separate booking login page.

### Invoice PDF missing logo (HTML shows it)

The view page uses a normal `<img>` tag. PDF embedding needs to decode `php/storage/brand/logo-print.png`. If PHP **GD** is disabled (common on AMPPS), booking now decodes PNG without GD (same approach as ERP). Re-download the PDF after updating code.

### 502 on “Pay now” / `gateway/order`

If `storage/logs/app-*.log` shows `SSL certificate problem: self-signed certificate in certificate chain`, PHP’s curl cannot verify Razorpay’s HTTPS certificate. For **local staging only**, add to `.env`:

```env
GATEWAY_SSL_VERIFY=false
```

(With `APP_ENV=staging` this is allowed; production always verifies TLS.) Long-term fix: point `curl.cainfo` in `php.ini` to a current CA bundle (e.g. from [curl.se/ca](https://curl.se/docs/caextract.html)).

### Razorpay says paid but booking still pending

After a server glitch, use **“I paid — update my booking”** on the pay page (or pay again — verify will reconcile from Razorpay’s API). For booking **SJT-2026-000001** that paid before the verify bug was fixed, open that booking’s pay URL and click that button once.

## Common issues

| Symptom | Fix |
|---------|-----|
| Redirect to `https://localhost/...` and broken page | `.htaccess` skips HTTPS only for `localhost` / `127.0.0.1` — use those hosts, not another name |
| "Session expired" on forms | `APP_URL` must match the URL in the browser bar |
| `Access denied for user 'root'@'localhost'` (1698) with `DB_HOST=127.0.0.1` | On this PC, **WSL** may own `127.0.0.1:3306`. Use **`DB_HOST=localhost`** so PHP hits **AMPPS** MySQL, or stop WSL MySQL |
| Other MySQL login errors | Start MySQL in AMPPS; default password `mysql`, or reset in AMPPS MySQL settings |
| `Unknown database 'sjt_temple_blr'` | Create the database in phpMyAdmin (`http://localhost:8080/phpMyAdmin` or AMPPS link), then `migrate` |

## Before uploading to the server

Restore production values in `.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, hosting DB user/password, and live or test Razorpay keys as appropriate. **Do not upload** the repo root `.env` override file if it contains local-only DB settings.
