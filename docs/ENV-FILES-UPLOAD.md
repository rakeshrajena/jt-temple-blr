# Production `.env` and one-time install

## Upload these files

| Local | Server |
|-------|--------|
| `crm/.env` | `public_html/crm/.env` |
| `Booking/.env` | `public_html/Booking/.env` |
| `Streaming/.env` | `public_html/Streaming/.env` |

Permission **600**. **Do not upload** `*/.env.local` or repo-root `public_html/.env` (they override DB with `root` and break Booking/donation).

## CRM and Booking must match

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (or `DB_PASS`)
- `INSTALL_TOKEN` (remove from both after install)

**Install reads `crm/.env` for MySQL** — never use `DB_USER=root` on the host.

## Schema-only install (no data migration)

In `crm/.env` set:

```ini
INSTALL_SCHEMA_ONLY=1
```

Then open `https://sjtbengaluru.org/crm/install.php` (or `Booking/install.php`):

- Leave **Schema only** checked (default).
- Tick **Run Booking migrations** for booking tables (001–010).
- Do **not** tick “Replace devotees/books snapshot”.
- Enter `INSTALL_TOKEN`, submit.

This creates/updates **tables and columns only** — no demo seed, no books snapshot, no legacy puja data copy.

Optional: create the first booking admin on the form, or add users in CRM later.

After success:

1. Delete `crm/install.php`, `Booking/install.php`, `crm/install_db_probe.php`.
2. Remove `INSTALL_TOKEN` and `INSTALL_SCHEMA_ONLY` from `crm/.env`.

## Before upload

```bash
php crm/bin/verify_env_sync.php
```

On server after upload (delete probes when done):

| App | Probe URL |
|-----|-----------|
| CRM | `https://sjtbengaluru.org/crm/install_db_probe.php` |
| Booking | `https://sjtbengaluru.org/Booking/install_db_probe.php` |
| Donation | `https://sjtbengaluru.org/Booking/donation/install_db_probe.php` (same DB as Booking) |
| Streaming | No DB — only `Streaming/.env` |

Each probe should show `local dev overrides active: no` on production, `effective DB_USER` = cPanel user (not `root`), `pdo: connected`.

Local checklist: `php crm/bin/env_deploy_probes.php`

## `.htaccess`

Upload root `.htaccess`, `crm/.htaccess`, `Booking/.htaccess`, `Streaming/.htaccess`. They block direct download of `.env` and route requests to each app’s `index.php`. `install.php` is allowed until you delete it.