# Shree Jagannath Temple — Admin Management System

A working Flask + database application for temple administration: inventory,
food stock, deity vastra, donations, expenses, bank reconciliation, and
monthly subscription billing with SMS/email payment links.

This is the **real, working application** — a database, a Flask backend,
and full templates — not a static mockup.

---

## Quick Start (local, SQLite — zero setup)

```bash
pip install -r requirements.txt --break-system-packages   # or use a venv
python3 app.py
```

Then open **http://localhost:5055** and log in with:

| Username | Password    | Role  |
|----------|-------------|-------|
| admin    | temple@123  | Admin |
| ramesh   | ramesh@123  | Admin |
| staff1   | staff@123   | Staff |

The database (`temple.db`) is created and seeded automatically on first run —
delete it and restart the app any time to reset to fresh demo data.

**Start here after logging in:** click **⭐ Demo Overview** in the sidebar
for a single-page summary of everything the system tracks.

---

## What's Included

| Module | What it does |
|---|---|
| **Dashboard** | Live KPIs across every module |
| **Inventory** | Hardware/equipment tracking, condition, location, source |
| **Food Stock** | Raw food items with add/use logging, plus a Food Coupon Generator (cost-tracked, sequential serial numbers, print-ready PDF) |
| **Deity Vastra** | Cloth inventory per deity, in-store/in-use/retired |
| **Donations** | Cash + in-kind donations, PDF receipt generation on button press |
| **Subscriptions** | Recurring seva plans, invoice generation, bulk SMS/email sending with secure payment links, public pay page, auto-reconciliation into Donations |
| **Expenses** | Categorized expense tracking |
| **Bank & Reconciliation** | Upload a CSV/Excel statement; auto-matches credits to donations and debits to expenses within a 3-day/exact-amount window; unmatched items get a manual-link UI |
| **Reports** | 6 printable reports (donations, expenses, inventory, food, vastra, reconciliation), each with a Print button |
| **Users** | Admin/Staff accounts, capped at 10 active users |

---

## Monthly Subscriptions — How It Works

1. **Generate Invoice** on a subscriber → creates a billing record with a unique, secure payment token.
2. **Send** (or select several and **Send Selected**) → texts/emails the devotee a link like
   `http://yourdomain/pay/<token>`.
3. The devotee opens the link (no login required), picks a payment method, and pays.
4. The invoice flips to **Paid**, a matching donation record is created automatically, and it shows up
   immediately in Donations, the Dashboard, and every report — no manual re-entry.

### What's real vs. simulated right now

| Step | Status |
|---|---|
| Invoice creation, tokens, status tracking, bulk selection | **Fully working** |
| Public payment page + success page | **Fully working** (UI + DB update) |
| Database update on payment, mirroring into Donations | **Fully working** |
| **Sending the actual SMS** | **Simulated** — logs the message + link to the console/`server.log` instead of calling a real provider |
| **Sending the actual email** | **Simulated** — same, logged instead of sent |
| **Processing the actual payment** | **Simulated** — marks the invoice Paid immediately; a real deployment would verify a gateway signature first |

To go live, you only need to change **three functions** — the rest of the app doesn't change:

1. `_send_sms_stub()` in `app.py` → replace with a real Twilio (or similar) API call
2. `_send_email_stub()` in `app.py` → replace with a real SMTP/SendGrid/SES call
3. `pay_invoice_confirm()` in `app.py` → replace the "always succeeds" logic with your payment
   gateway's real callback/webhook verification (Razorpay, PayU, Stripe, etc.)

Each function has the real integration pattern written as a comment directly above it in the code.

---

## Moving to MySQL in Production

This demo runs on SQLite for zero-setup local testing. `schema_mysql.sql` contains the identical
table/column structure for a real MySQL server. To switch:

1. Run `schema_mysql.sql` against your MySQL server:
   ```bash
   mysql -u youruser -p < schema_mysql.sql
   ```
2. In `database.py`, replace the `sqlite3` connection in `get_db()` with a `pymysql` connection
   (install with `pip install pymysql`), e.g.:
   ```python
   import pymysql
   def get_db():
       return pymysql.connect(
           host="localhost", user="youruser", password="yourpass",
           database="temple_admin", cursorclass=pymysql.cursors.DictCursor
       )
   ```
3. Everywhere the code uses `?` as a SQL placeholder, MySQL/PyMySQL also accepts `%s` — either
   run a find-and-replace, or use PyMySQL's `paramstyle` compatibility. All queries in `app.py`,
   `reports.py`, and `reconciliation.py` are plain SQL, no SQLite-specific syntax.
4. Remove the `_seed()` demo data call in `database.py` if you don't want sample data in production.

---

## Deploying for Real (so the payment links work from anywhere)

Right now, `pay_invoice()`'s `_external=True` link generation uses whatever host Flask thinks it's
running on. For links texted to real phones to work, you need:

- A real domain (e.g. `donate.yourtemple.org`) pointed at your server
- HTTPS (a payment page must be served over HTTPS)
- A production WSGI server instead of Flask's dev server, e.g.:
  ```bash
  pip install gunicorn --break-system-packages
  gunicorn -w 4 -b 0.0.0.0:8000 app:app
  ```
- A reverse proxy (Nginx) in front of Gunicorn for TLS termination

---

## Project Structure

```
app.py                  Flask application — all routes
database.py              SQLite schema + seed data (dev/demo)
schema_mysql.sql          Equivalent MySQL schema for production
receipt_generator.py      PDF donation receipt generation (reportlab)
reconciliation.py         Bank statement parsing + auto-matching logic
reports.py                Report queries + dashboard KPI aggregation
templates/                All HTML templates (Jinja2)
static/css/style.css      Hand-written CSS, no external CDN dependency
static/logo.png           Temple logo, used across UI, receipts, and payment pages
uploads/                  Bank statement uploads land here
receipts/                 Generated PDF receipts land here
```

---

## Known Limitations (by design, for this demo stage)

- Flask's built-in dev server is used for `python3 app.py` — fine for local testing,
  **do not use in production** without Gunicorn/uWSGI behind a real web server.
- No rate-limiting or CSRF protection yet on forms — add `Flask-WTF` or similar before public deployment.
- Passwords are hashed (Werkzeug's `generate_password_hash`), but there's no password-reset flow yet.
- Change `app.secret_key` in `app.py` to a real random secret before deploying anywhere reachable
  by the public.
