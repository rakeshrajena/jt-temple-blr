# Deployment on the temple's cPanel hosting

sjtbengaluru.org is a cPanel account (the home page is cPanel's "Coming Soon" placeholder and
the owner login is at https://sjtbengaluru.org/cpanel). These steps use only standard cPanel
tools. Nothing on the rest of the domain is changed; the app only adds a `Booking` folder.

**Order of work:** staging → full test → temple approval → production.

---

## 0. What you need

- cPanel login for sjtbengaluru.org (from the temple / hosting provider).
- The release zip of the **`booking/`** folder (e.g. `booking-<version>.zip`).
- 30–60 minutes.

## 1. Check the hosting (once)

1. **cPanel › MultiPHP Manager**: set the domain to **PHP 8.1 or newer** (8.2/8.3 recommended).
2. **cPanel › Select PHP Version › Extensions** (CloudLinux hosts): make sure these are ticked:
   `pdo_mysql`, `mbstring`, `gd`, `curl`, `dom`, `xml`, `fileinfo`, `openssl`, `zip`.
3. **cPanel › MultiPHP INI Editor**: `memory_limit` ≥ 256M, `upload_max_filesize` ≥ 4M,
   `max_execution_time` ≥ 60.
4. **cPanel › SSL/TLS Status**: sjtbengaluru.org must have a valid certificate (run AutoSSL if
   not). The app forces HTTPS.

## 2. Staging installation

Staging lives at **https://sjtbengaluru.org/BookingStaging** with its own database, protected by a
password so the public cannot see it.

### 2.1 Database
**cPanel › MySQL Database Wizard**
- Database: `sjtstage` (cPanel prefixes it, e.g. `sjtbeng_sjtstage`)
- User: `sjtstage` with a long generated password
- Privileges: **ALL PRIVILEGES** on this database only.

### 2.2 Upload the application (single folder)
**cPanel › File Manager**
1. In `public_html`, create a folder named exactly **`BookingStaging`**.
2. Upload the zip into `public_html/BookingStaging`, right-click › **Extract**, and move the
   extracted files up so the folder directly contains `index.php`, `install.php`, `.htaccess`,
   `app/`, `assets/`, `bin/`, `migrations/`, `storage/`, `.env`.
   There is **no** `public/` subfolder and **no** `app-path.php` — the app runs from one folder,
   exactly like the ERP `php/` app. The `.htaccess` inside the folder protects `.env`, `app/`,
   `storage/`, etc.

### 2.3 Configuration file
1. In `BookingStaging`, copy `.env.example` to `.env` (enable *Show Hidden Files* in File Manager
   settings to see dot files).
2. Edit `.env`:
   ```
   APP_ENV=staging
   APP_DEBUG=false
   APP_URL=https://sjtbengaluru.org/BookingStaging
   APP_KEY=<64 random characters — run: php bin/console.php key>
   INSTALL_TOKEN=<16+ random characters>
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_NAME=<cpaneluser>_sjtstage
   DB_USER=<cpaneluser>_sjtstage
   DB_PASS=<the password from 2.1>
   ```
   Leave the WhatsApp and gateway values empty for now.
3. Right-click `.env` › **Change Permissions** › `600`.
4. Make sure `storage` and every folder inside it are writable (`755`, owner = your cPanel user).
5. **cPanel › Directory Privacy** › `public_html/BookingStaging` › enable password protection and
   create a user for the testers. (Remove this only for production, never on staging.)

### 2.4 Create the tables and the first administrator

**Option A — Web installer (no SSH):** after `.env` is configured with `INSTALL_TOKEN` (16+ chars) and `APP_KEY`, open  
`https://sjtbengaluru.org/BookingStaging/install.php`, enter the setup key, fill in the first admin, and submit.  
Then delete `install.php` and remove `INSTALL_TOKEN` from `.env`.

**Option B — Terminal** (cPanel › Advanced › Terminal) or SSH:
```bash
cd ~/public_html/BookingStaging
php bin/console.php migrate
php bin/console.php create-admin admin "Temple Administrator" super
php bin/console.php check
```
If `php` is an older version on the command line, use the full path, e.g.
`/opt/cpanel/ea-php83/root/usr/bin/php` (or `/usr/local/bin/ea-php83`).

**Without Terminal:** in **cPanel › Cron Jobs** add a one-off job that runs a minute from now:
`cd ~/public_html/BookingStaging && SJT_ADMIN_PASSWORD='Choose-A-Strong-Pass1' php bin/console.php migrate && SJT_ADMIN_PASSWORD='Choose-A-Strong-Pass1' php bin/console.php create-admin admin "Temple Administrator" super > ~/sjt-setup.log 2>&1`
Check `~/sjt-setup.log`, then **delete the cron job** and change the password after first login.

### 2.5 Smoke test
- https://sjtbengaluru.org/BookingStaging/health → `{"status":"ok"}`
- https://sjtbengaluru.org/BookingStaging → booking home page
- https://sjtbengaluru.org/BookingStaging/admin → sign in
- The dashboard shows a "Before going live" list — work through it (docs/CONFIGURATION.md).

### 2.7 Scheduled jobs (cPanel › Cron Jobs)
| When | Command |
|---|---|
| Every 10 minutes | `cd ~/public_html/BookingStaging && php bin/console.php notifications:retry >/dev/null 2>&1` |
| Daily 03:15 | `cd ~/public_html/BookingStaging && php bin/console.php maintenance >/dev/null 2>&1` |
| Daily 02:30 | `cd ~/public_html/BookingStaging && bash bin/backup.sh >/dev/null 2>&1` |

### 2.8 End-to-end test on staging
Follow **docs/TESTING.md › Staging acceptance checklist**, including a real ₹1 UPI payment and
WhatsApp messages to a test phone. Record the results and get written approval from the temple.

---

## 3. Production installation (after approval)

Repeat section 2 with these differences:

| Item | Staging | Production |
|---|---|---|
| Folder | `public_html/BookingStaging` | `public_html/Booking` (capital B) |
| Database | `<user>_sjtstage` | new `<user>_sjtbooking` (fresh — no test bookings) |
| `.env` APP_ENV / APP_URL | `staging` / …/BookingStaging | `production` / `https://sjtbengaluru.org/Booking` |
| Directory Privacy | on | **off** |
| Cron jobs | staging paths | production paths (and remove the staging backup job if staging is retired) |

Then re-enter the settings (temple details, UPI, WhatsApp, prices) in the production dashboard,
repeat the ₹1 UPI test and one WhatsApp test, and switch the temple website's "Book now" links to
`https://sjtbengaluru.org/Booking`.

### Optional: accept lowercase /booking
Add at the **top** of `public_html/.htaccess` (create it if it does not exist):
```apache
RewriteEngine On
RewriteRule ^booking(/.*)?$ /Booking$1 [R=301,L,NC]
```
(Do not add `NC` if your server treats `Booking` and `booking` as the same folder; test both URLs.)

### When the main website is built later
The booking app is self-contained in `public_html/Booking`. A WordPress or other site installed in
`public_html` will not affect it, because Apache uses the `.htaccess` inside `Booking/` for those
URLs. Keep the `Booking` folder when replacing the "Coming Soon" page.

---

## 4. Updating to a new version

1. Take a backup: `bash bin/backup.sh`.
2. Upload the new zip, extract to a temporary folder, then copy over **everything except** `.env`
   and `storage/` into `public_html/Booking` (the whole folder lives in one place now).
3. Run `php bin/console.php migrate` (migrations only add tables/columns; nothing is deleted).
4. Open `/Booking/health` and the dashboard.
Always try the update on staging first.

## 5. Troubleshooting

| Symptom | Fix |
|---|---|
| "This application needs PHP 8.1 or newer" | MultiPHP Manager › select PHP 8.1+ for the domain |
| 500 error on every page | Check `public_html/Booking/storage/logs/` and `php-errors.log` |
| 404 for /Booking/book | `.htaccess` missing in `public_html/Booking` (enable *Show Hidden Files* and copy it) |
| "Session expired" on every form | `storage/sessions` not writable, or APP_URL scheme/host does not match the address in the browser |
| PDF download fails | `storage/cache` not writable, or `memory_limit` too low |
| Wrong times | `APP_TIMEZONE=Asia/Kolkata` in `.env` |
| Behind Cloudflare: everyone shares one rate limit | set `TRUSTED_IP_HEADER=CF-Connecting-IP` |
