# Shree Jagannath Temple - Admin (PHP 8)

Temple administration for Sarjapura, Bengaluru: inventory, food stock, deity vastra, donations, expenses, bank reconciliation, reports, and monthly seva subscriptions.

This is the PHP 8 port of the Flask app in `../python/`. It uses MySQL on the local AMPPS server.

## Run it

1. Start Apache and MySQL in AMPPS.
2. Open [http://localhost/jt_blr/jt-temple-blr/crm/](http://localhost/jt_blr/jt-temple-blr/crm/).

On this computer the first request creates the `sjt_temple_blr` database, the tables in `schema.sql`, and the demo records. On the hosting server, upload this `crm` folder, including `storage`, and open `install.php`. Keep the server’s own `.env`. Do not upload the `.env` that sits next to this folder.

`storage/install/books.jsonl` is the saved books. It is not in git. This copy has the gifts and no receipt generated yet. If devotees are already on the server, tick replace so that file is loaded. `storage/selections.json` is the form choices, including each puja purpose and its amount. Uploading the folder replaces that file. It is not loaded by the replace tick. Then remove `INSTALL_TOKEN` from the server `.env`. The full steps are in `docs/USER-GUIDE.md`.

Before uploading, run `php bin/export-books.php` to refresh the saved books, then `php bin/verify-install.php`. The check builds a temporary `<DB_NAME>_install_check` database from `schema.sql` and the saved books, compares every table, column, and row count with the live database, and always drops the temporary database. It exits with 1 and lists each difference when the install would not match. `tests/install_check_test.php` runs the same check.

| Username | Password | Role |
|---|---|---|
| admin | temple@123 | Admin |
| ramesh | ramesh@123 | Admin |
| treasurer | treasurer@123 | Treasurer |
| staff1 | staff@123 | Staff |

Staff can use every module except Users. A Treasurer can approve an expense, a cash deposit or withdrawal, or an opening balance up to ₹10,000. Only an Admin can add or deactivate accounts, and an Admin approves amounts above that limit. There can be at most 10 active users.

## Database

Credentials are read from **`crm/.env`** (see `env.example`; loaded via `config.php`). Local AMPPS defaults are usually `localhost`, user `root`, password `mysql`.

| Setting | Value |
|---|---|
| Host | localhost |
| User | root |
| Password | mysql |
| Database | sjt_temple_blr |
| Timezone | Asia/Kolkata |

Use one MySQL database for CRM and Booking when they are integrated (same `DB_NAME` in both `.env` files).

### Schema upgrades (existing production DBs)

Every signed-in CRM request calls `migrate_schema()` from `includes/db.php`. That applies **additive** changes only (`CREATE TABLE IF NOT EXISTS`, missing columns via `ensure_*` helpers). **No automatic row backfill** runs.

| Item | Notes |
|---|---|
| **`vastra_movements`** | Created on first request after code deploy if missing. Stores Issue / Return / Retire with date, qty, and notes. History starts empty until staff use **Record a movement**. |
| **`vastra_items`** | No structural change in recent vastra work — existing lines are unchanged. |
| **Reports / UI** | PHP views and exports only — no DB changes. |

After deploy you can run:

```bash
php bin/preflight_deploy.php
```

Or confirm in MySQL: `SHOW TABLES LIKE 'vastra_movements';`

Fresh installs get everything from `schema.sql` via `install.php` or first visit.

## Modules

| Module | What it does |
|---|---|
| Dashboard | Totals for donations, expenses, stock alerts, and subscriptions |
| Inventory | Quantity, rate, value, condition, and location. Issue, return, damage, loss, and retired. A purchase posts stock and the payment together after approval |
| Food stock | Add items, log stock in and out, low-stock flags. Kitchen use above 5 units waits for approval |
| Coupons | Under Temple, and also linked from Food stock. One generate form covers a single coupon and a batch. Quantity 1 prints at once and does not wait for approval. A larger quantity waits. The coupon name and the purpose use the Puja purpose choices in Settings. Choosing a coupon name fills that amount. Choosing a purpose does not. |
| Deity vastra | **Add vastra item** (new stock is In Store). **Record a movement**: Issue (store → in use), Return (in use → store, merges matching store line), Retire. Partial qty splits ledger lines. Summary table by deity, item, and colour; collapsible stock lines; recent movements from `vastra_movements` |
| Donations | Cash and in-kind gifts. In-kind food, vastra, and inventory update those registers. Receipts are PDF. Generate receipt stays available and overwrites the same number. An edit waits for approval: one person within 24 hours, both a Treasurer and an Admin after that |
| Donors | One page per devotee: gifts, receipt numbers, PAN, and pledge versus amount received. The page prints as the yearly statement |
| Receipts | List of generated receipt PDFs. Anyone signed in can open one. An Admin can download a zip. Cancelling a receipt keeps the number and waits for approval |
| Subscriptions | Recurring seva plans, invoices, and a public pay link |
| Expenses | Categorised spending, with a voucher number, an optional bill, a UPI id, and cheque details. A new expense waits for approval before it enters the books |
| Cash book | Receipt and payment columns for cash and bank, with opening and closing balances. Opening balance, carry forward, deposits, and withdrawals wait for approval |
| Corrections | Keep the original donation or expense and add a second line, after approval |
| Day book | The same period as one date-wise list, with who entered each line |
| Ledger | One balance per donation purpose and expense category: money received, money spent, and what remains |
| Bank reconciliation | Upload a CSV or Excel statement. Credits match donations and debits match expenses when the amount is the same and the date is within 3 days. Anything left over can be linked by hand |
| Reports | Hub cards for donations, expenses, inventory, food, vastra, and reconciliation. Each report defaults to the **last calendar month**, supports refine filters and table search, and exports **PDF**, **CSV**, and **Excel** (`.xlsx` when PHP `zip` extension is on; otherwise SpreadsheetML `.xls`). Browser print still works |
| App contributors | Cards for the people behind the app. Everyone signed in can read them. Only an Admin can add, update, or remove a person |
| Users | Admin-only accounts |
| Settings | Admin-only temple name, place, logo, approval limits, form choices, outgoing mail, and the WhatsApp Web message. Puja purpose stores a name and an amount for coupons. WhatsApp opens in the browser; there is no WhatsApp API |

### Subscriptions and payment links

1. Generate an invoice for an active subscriber.
2. Send it. The message is written to `storage/logs/notifications.log`. If SMTP is saved in Settings, it is also emailed. WhatsApp on the invoice row opens WhatsApp Web with the message filled in.
3. The devotee opens `/pay/<token>` without signing in, chooses UPI, card, or netbanking, and confirms.
4. The invoice becomes Paid and a matching donation is created.

The pay page records the payment immediately. A live gateway would verify the payment before that update.

## Project layout

```
crm/
 index.php           Front controller
 config.php          Constants; DB from .env
 env.example         Template for server .env
 schema.sql          MySQL tables (new installs)
 includes/           Auth, queries, PDFs, reports, vastra, bank matching, seed data
 views/              Pages
 static/             CSS and logo
 bin/                export-books, verify-install, preflight_deploy
 tests/              PHPUnit-style CLI tests (vastra, reports export, …)
 storage/receipts/   Donation receipt PDFs
 storage/coupons/    Coupon batch PDFs
 storage/uploads/    Bank statements
 storage/logs/       Notification log and error log
 manage_data/        Admin maintenance UI (see manage_data/README.md)
```

`includes/` and `storage/` are not served as web pages.

## Notes

- Passwords are stored with `password_hash()`.
- Forms use a session token so a third-party site cannot submit them.
- Receipt and coupon PDFs are generated in PHP. Excel bank statements may use Windows `tar` when PHP has no Zip extension; CSV upload works without that. Report Excel export prefers `.xlsx` and falls back to `.xls` when Zip is missing (common on some AMPPS builds).
- To reset the demo, drop the `sjt_temple_blr` database in AMPPS MySQL and reload the site.
- Receipt PDFs, coupon PDFs, bank uploads, and logs under `storage/` are created at runtime and are listed in the repo `.gitignore`.

## GoDaddy: `/crm` does not open (but `/Booking` works)

Linux hosting is **case-sensitive**. The folder name must match the URL exactly (`crm/` → `https://yoursite.org/crm/`, `Booking/` → `https://yoursite.org/Booking/`).

1. **Upload the full `crm/` tree** to `public_html/crm/` (not only `index.php`): `includes/`, `views/`, `static/`, `storage/` (with writable subfolders), `.htaccess`, and `index.php`.
2. **`crm/.env` on the server** with real `DB_*` credentials (copy from `env.example`). Without it you may get 500 or “database connection failed”.
3. **`storage/` permissions** 755 on folders, writable by PHP (`receipts`, `logs`, `uploads`, etc.).
4. **First-time DB:** open `https://yoursite.org/crm/install.php` with `INSTALL_TOKEN`, then remove `install.php` and `INSTALL_TOKEN` after setup (same pattern as Booking).
5. **Existing DB after code update:** open any CRM URL once so `migrate_schema()` runs; no manual SQL file required for `vastra_movements`.
6. **Still 403?** In cPanel File Manager, confirm `public_html/crm/index.php` exists. Enable **Show Hidden Files** and confirm `crm/.htaccess` uploaded.
7. **Still 500?** Read `crm/storage/logs/app.log` and the server PHP error log in cPanel.

If you only deployed Booking and the home page so far, **`/crm` will not work until you upload `crm/`**.