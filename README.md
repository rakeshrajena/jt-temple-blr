# Shree Jagannath Temple — Bengaluru

Inventory and donation management for Shree Jagannath Temple, Sarjapura, Bengaluru.

Two copies of the same admin app live in this repo:

| Folder | Stack | Use it when |
|---|---|---|
| [`php/`](php/README.md) | PHP 8 and MySQL | This is the app served by AMPPS |
| [`python/`](python/README.md) | Flask and SQLite | The original app, for local Python runs |

## PHP (AMPPS)

1. Start Apache and MySQL in AMPPS.
2. Open [http://localhost/jt_blr/jt-temple-blr/php/](http://localhost/jt_blr/jt-temple-blr/php/).

The first request on this computer creates the `sjt_temple_blr` database, the tables, and the demo records. Database settings are in `php/config.php` (localhost, user `root`, password `mysql`). On the hosting server, upload the `php` folder including `storage`, keep that server’s `.env`, and open `install.php`. Tick replace when devotees are already there so the saved books are loaded. The steps are in [docs/USER-GUIDE.md](docs/USER-GUIDE.md).

| Username | Password | Role |
|---|---|---|
| admin | temple@123 | Admin |
| ramesh | ramesh@123 | Admin |
| treasurer | treasurer@123 | Treasurer |
| staff1 | staff@123 | Staff |

Staff can use every module except Users. A Treasurer can approve up to ₹10,000. Stock written off above 5 units also waits for approval. Only an Admin can add or deactivate accounts, and an Admin approves amounts above that limit.

Generated receipt PDFs, coupon PDFs, uploaded bank statements, and logs stay in `php/storage/` and are not part of the git history. See [php/README.md](php/README.md) for modules, subscriptions, and layout. See [FEATURES.md](FEATURES.md) for what the PHP app already does and what is still to add.

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
