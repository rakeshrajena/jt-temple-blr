# Shree Jagannath Temple — Bengaluru

Inventory, donation, and online booking management for Shree Jagannath Temple, Sarjapura, Bengaluru.

Three apps live in this repo:

| Folder | Stack | Use it when |
|---|---|---|
| [`php/`](php/README.md) | PHP 8 and MySQL | The admin app (inventory, donations) served by AMPPS |
| [`python/`](python/README.md) | Flask and SQLite | The original admin app, for local Python runs |
| [`Booking/`](Booking/README.md) | PHP 8.1+ and MySQL | Public online booking (pujas, prasadam) for the temple site |

The root [`bookingPreview.html`](bookingPreview.html) is a standalone UI prototype of the booking flow, for stakeholder sign-off.

## PHP (AMPPS)

1. Start Apache and MySQL in AMPPS.
2. Open [http://localhost/jt_blr/jt-temple-blr/php/](http://localhost/jt_blr/jt-temple-blr/php/).

The first request on this computer creates the `sjt_temple_blr` database, the tables, and the demo records. Database settings are in `php/config.php` (localhost, user `root`, password `mysql`). On the hosting server, upload the `php` folder including `storage`, keep that server’s `.env`, and open `install.php`. Tick replace when devotees are already there so `storage/install/books.jsonl` is loaded. Form choices, including puja purposes and amounts, are in `storage/selections.json` and come with the folder. The steps, and full-screen pictures of the current registers, are in [docs/USER-GUIDE.md](docs/USER-GUIDE.md). App contributors is visible to everyone who is signed in. Only an Admin can change those cards.

| Username | Password | Role |
|---|---|---|
| admin | temple@123 | Admin |
| ramesh | ramesh@123 | Admin |
| treasurer | treasurer@123 | Treasurer |
| staff1 | staff@123 | Staff |

Staff can use every module except Users. A Treasurer can approve up to ₹10,000. Stock written off above 5 units also waits for approval. Only an Admin can add or deactivate accounts, and an Admin approves amounts above that limit.

Generated receipt PDFs, coupon PDFs, uploaded bank statements, and logs stay in `php/storage/` and are not part of the git history. See [php/README.md](php/README.md) for modules, subscriptions, and layout. See [FEATURES.md](FEATURES.md) for what the PHP app already does and what is still to add.

## Online booking (PHP 8.1+)

The `Booking/` folder is the production online booking application. Devotees book pujas, ceremonies, and food/prasadam coupons, pay via a dynamic UPI QR (or optional Razorpay), and receive PDF invoices and WhatsApp notifications. An admin dashboard covers payment verification, approvals, rescheduling, refunds, and reports.

Quick start:

1. From `Booking/`, copy `.env.example` to `.env`, and set `APP_KEY` and `INSTALL_TOKEN`.
2. Point it at the same MySQL database as `php/` (e.g. `sjt_temple_blr`) — booking adds its own tables and does not touch ERP `users`.
3. Open `Booking/install.php` in the browser, or run `php bin/console.php migrate`.

The full booking flow, deployment, and ERP integration details are in [Booking/README.md](Booking/README.md). Deploy instructions: [Booking/docs/DEPLOYMENT-CPANEL.md](Booking/docs/DEPLOYMENT-CPANEL.md). Pre-launch checklist: [Booking/docs/TEMPLE-ACTION-ITEMS.md](Booking/docs/TEMPLE-ACTION-ITEMS.md).

## Python (Flask)

```bash
cd python
pip install -r requirements.txt
python app.py
```

Then open [http://localhost:5055](http://localhost:5055) and sign in with the same demo accounts. The SQLite file `python/temple.db` is created on first run and is not committed. See [python/README.md](python/README.md).

## What stays off GitHub

`.gitignore` leaves out files that are created while the app runs:

- Receipt and coupon PDFs
- Uploaded bank statements
- Application and notification logs
- The Python SQLite database, bytecode, and virtualenv
- `.env` files, if you add them later
- Booking `.env`, uploads, sessions, cache, backups, and logs (its own `.gitignore`)
