# Shree Jagannath Temple — Admin (PHP 8)

Temple administration for Sarjapura, Bengaluru: inventory, food stock, deity vastra, donations, expenses, bank reconciliation, printable reports, and monthly seva subscriptions.

This is the PHP 8 port of the Flask app in `../python/`. It uses MySQL on the local AMPPS server.

## Run it

1. Start Apache and MySQL in AMPPS.
2. Open [http://localhost/jt_blr/jt-temple-blr/php/](http://localhost/jt_blr/jt-temple-blr/php/).

The first request creates the `jt_blr` database, the tables in `schema.sql`, and the demo records.

| Username | Password | Role |
|---|---|---|
| admin | temple@123 | Admin |
| ramesh | ramesh@123 | Admin |
| treasurer | treasurer@123 | Treasurer |
| staff1 | staff@123 | Staff |

Staff can use every module except Users. A Treasurer can approve an expense, a cash deposit or withdrawal, or an opening balance up to ₹10,000. Only an Admin can add or deactivate accounts, and an Admin approves amounts above that limit. There can be at most 10 active users.

## Database

Credentials match the local AMPPS defaults (`localhost`, user `root`, password `mysql`). This app uses its own database, `jt_blr`, so it does not share tables with other projects.

| Setting | Value |
|---|---|
| Host | localhost |
| User | root |
| Password | mysql |
| Database | jt_blr |
| Timezone | Asia/Kolkata |

Change these in `config.php` if the server credentials differ.

## Modules

| Module | What it does |
|---|---|
| Dashboard | Totals for donations, expenses, stock alerts, and subscriptions |
| Inventory | Quantity, rate, value, condition, and location. Issue, return, damage, loss, and retired. A purchase posts stock and the payment together after approval |
| Food stock | Add items, log stock in and out, low-stock flags. Kitchen use above 5 units waits for approval |
| Food coupons | Sequential batches and a printable PDF. The face value waits for approval and does not enter the cash book. Name, cost, and quantity can be edited or the batch removed |
| Deity vastra | Cloths by deity, marked In Store, In Use, or Retired |
| Donations | Cash and in-kind gifts. In-kind food, vastra, and inventory update those registers. Receipts are PDF. A gift can be applied to a pledge |
| Donors | One page per devotee: gifts, receipt numbers, PAN, and pledge versus amount received. The page prints as the yearly statement |
| Receipts | List of generated receipt PDFs. Anyone signed in can open one. An Admin can download a zip. Cancelling a receipt keeps the number and waits for approval |
| Subscriptions | Recurring seva plans, invoices, and a public pay link |
| Expenses | Categorised spending, with a voucher number, an optional bill, a UPI id, and cheque details. A new expense waits for approval before it enters the books |
| Cash book | Receipt and payment columns for cash and bank, with opening and closing balances. Opening balance, carry forward, deposits, and withdrawals wait for approval |
| Corrections | Keep the original donation or expense and add a second line, after approval |
| Day book | The same period as one date-wise list, with who entered each line |
| Ledger | One balance per donation purpose and expense category: money received, money spent, and what remains |
| Bank reconciliation | Upload a CSV or Excel statement. Credits match donations and debits match expenses when the amount is the same and the date is within 3 days. Anything left over can be linked by hand |
| Reports | Donations, expenses, inventory, food, vastra, and reconciliation. Each report prints from the browser |
| Users | Admin-only accounts |

### Subscriptions and payment links

1. Generate an invoice for an active subscriber.
2. Send it. The SMS and email text is written to `storage/logs/notifications.log` (nothing is sent to a phone or inbox).
3. The devotee opens `/pay/<token>` without signing in, chooses UPI, card, or netbanking, and confirms.
4. The invoice becomes Paid and a matching donation is created.

The pay page records the payment immediately. A live gateway would verify the payment before that update.

## Project layout

```
php/
  index.php              Front controller
  config.php             Database and app settings
  schema.sql             MySQL tables
  includes/              Auth, queries, PDFs, bank matching, seed data
  views/                 Pages
  static/                CSS and logo
  storage/receipts/      Donation receipt PDFs
  storage/coupons/       Coupon batch PDFs
  storage/uploads/       Bank statements
  storage/logs/          Notification log and error log
```

`includes/` and `storage/` are not served as web pages.

## Notes

- Passwords are stored with `password_hash()`.
- Forms use a session token so a third-party site cannot submit them.
- Receipt and coupon PDFs are generated in PHP. Excel statements are read with Windows `tar` because this PHP build has no Zip extension. CSV upload works without that.
- To reset the demo, drop the `jt_blr` database in AMPPS MySQL and reload the site.
- Receipt PDFs, coupon PDFs, bank uploads, and logs under `storage/` are created at runtime and are listed in the repo `.gitignore`.
