# Which `.env` files go on the host

| Upload to server | Do not upload |
|------------------|---------------|
| `public_html/crm/.env` | Repo root `.env` (AMPPS overrides) |
| `public_html/Booking/.env` | |
| `public_html/Streaming/.env` | |

`home/index.html` has no `.env`; URLs are inline (`SJT_DONATION_URL`, etc.) — already production URLs.

After FTP/cPanel upload, run once (SSH if available):

```bash
php crm/bin/preflight_deploy.php
```

Before accepting real money, set **live** `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` in `Booking/.env` only.
