# Backup and recovery

## What must be protected
| Item | Where | Backed up by |
|---|---|---|
| Booking database (bookings, payments, audit, settings) | MySQL database | `bin/backup.sh` (daily) + cPanel backups |
| Uploaded static QR image | `storage/uploads/` | `bin/backup.sh` |
| Configuration and secrets | `.env` | `bin/backup.sh` (file archive) — keep an offline copy too |
| Application code | release zip | keep the zip of every installed version |

## Daily automatic backup
cPanel › Cron Jobs, daily at 02:30:
```
cd ~/public_html/Booking && bash bin/backup.sh >/dev/null 2>&1
```
This writes `storage/backups/sjt-db-YYYYmmdd-HHMM.sql.gz` (consistent `mysqldump --single-transaction`)
and `sjt-files-….tar.gz`, and deletes files older than 30 days (`KEEP_DAYS=60 bash bin/backup.sh`
to keep longer). Database credentials are passed through a private temporary file, not the
command line. The `storage/` folder is blocked from the web by the app's `.htaccess`.

**After the first night, check that a non-empty `.sql.gz` file exists** — the MySQL branch of the
script could not be executed in the development sandbox (no `mysqldump` there) and must be
confirmed once on the host. If `mysqldump` is not available to cron, use the cPanel option below.

## Off-site copies (required)
Backups on the same server do not protect against losing the server. At least weekly:
- cPanel › **Backup** › *Download a MySQL Database Backup* (and the home-directory backup), or
- download the latest files from `storage/backups/` and store them in the temple's Google Drive /
  external disk, encrypted if they leave the office (they contain devotees' names and phone numbers).

Many hosts also provide JetBackup (cPanel › JetBackup) with daily snapshots — confirm retention
with the hosting provider.

## Restore the database
1. Pick the backup file (e.g. `storage/backups/sjt-db-20261012-0230.sql.gz`).
2. Run:
   ```bash
   cd ~/public_html/Booking
   bash bin/restore.sh storage/backups/sjt-db-20261012-0230.sql.gz
   ```
   It asks you to type `RESTORE`, first takes a safety backup of the current data, loads the
   backup, then runs any pending migrations.
3. Without Terminal: cPanel › phpMyAdmin › select the database › *Import* › choose the `.sql.gz`.
4. Open the dashboard and check the latest bookings. Bookings made after the backup time are lost
   — compare with WhatsApp admin alerts and the bank statement to re-enter them manually.

## Full disaster recovery (new server)
1. Install PHP 8.1+ and create a database (docs/DEPLOYMENT-CPANEL.md §1–2.1).
2. Upload the same release zip, restore `.env` from the files backup (update DB credentials),
   restore `storage/uploads/`.
3. Import the latest database backup (step above), run `php bin/console.php migrate` and
   `php bin/console.php check`.
4. Upload the whole folder to `public_html/Booking` (flat deploy — no `public/` split or `app-path.php`), re-create cron jobs.
5. Update the WhatsApp webhook / Razorpay webhook URLs if the domain changed.

## Test the backups
Once a month, restore the latest backup into the **staging** database and open the staging
dashboard. A backup that has never been restored is not a backup.

## Data retention
Bookings are kept indefinitely for accounts. Logs older than 180 days and session files are removed
by the daily `maintenance` job. If the temple adopts a retention policy for personal data, older
bookings can be exported to CSV and anonymised on request.
