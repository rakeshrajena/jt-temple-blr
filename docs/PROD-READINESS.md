# Production readiness (public_html root)

Use this before uploading **jt-temple-blr** into hosting **document root** (`public_html/`), not inside an extra subfolder.

## Automated checks (run on your PC)

```powershell
.\scripts\pre-deploy.ps1
```

```bash
php crm/bin/preflight_deploy.php
php crm/bin/prod_url_smoke.php
```

| Check | What it validates |
|--------|-------------------|
| `verify-deploy-links.ps1` | Required files, no `jt_blr/localhost` hardcodes in web trees |
| `preflight_deploy.php` | DB schema, `APP_URL` / `ERP_APP_URL`, donation + manage_data folders, SMTP/gateway warnings |
| `prod_url_smoke.php` | CRM + manage_data URLs resolve to `/crm/...` at domain root |

## Upload layout

Contents of the repo go **directly** under `public_html/`:

| Path on server | App |
|----------------|-----|
| `index.html`, `assets/`, `site-links.js`, `home/` | Public website |
| `Booking/` | Online booking + admin |
| `Booking/donation/` | Standalone donations |
| `crm/` | Temple ERP |
| `crm/manage_data/` | Admin data maintenance (upload with `crm/`) |
| `Streaming/` | Live darshan |

**Do not upload:** `.env`, `.env.local`, `storage/logs`, session files, local uploads, `node_modules/`, git metadata.

## Server `.env` (create on host)

| File | Required URLs (domain root) |
|------|-----------------------------|
| `crm/.env` | `APP_URL=https://sjtbengaluru.org/crm`, `BOOKING_APP_URL=https://sjtbengaluru.org/Booking` |
| `Booking/.env` | `APP_URL=https://sjtbengaluru.org/Booking`, `ERP_APP_URL=https://sjtbengaluru.org/crm` |

Same MySQL `DB_*` in both files. At domain root, **do not** set `STAFF_SESSION_PATH` unless you use a subfolder install (then set parent path, e.g. `/jt_blr/jt-temple-blr`).

Optional: `MANAGE_DATA_KEY` in `crm/.env` — then every `manage_data` visit needs `?key=…`.

## How links stay correct at domain root

- **Home:** `index.html` sets `SJT_PUBLIC_BASE` from the browser URL → Book, Donate, gallery APIs, Streaming use **relative** `origin + path + Booking/...`.
- **CRM / manage_data:** Paths come from `SCRIPT_NAME` (`/crm/index.php`, `/crm/manage_data/...`) and from `APP_URL` in `.env` when generating links in email/CLI.
- **Booking:** `APP_URL` drives invoices, pay links, and webhooks.

Your current repo `.env` files already use `https://sjtbengaluru.org/...` — correct for production; on AMPPS, `crm/.env.local` only overrides **database** credentials locally.

## Post-upload (once)

1. `https://your-domain/crm/install.php` (or first visit) + `Booking/install.php` if needed — then remove `INSTALL_TOKEN` and delete/rename `install.php`.
2. CRM → **Settings → Identity** (logo, phones — powers home page API).
3. Writable: `crm/storage/*`, `Booking/storage/*`.

## Smoke test after go-live

Full list: [DEPLOY-PRODUCTION.md](DEPLOY-PRODUCTION.md#post-go-live-smoke-test).

Minimum:

- `/` — hero, identity, Donate / Book buttons  
- `/Booking/book`, `/Booking/donation/`, `/Booking/lookup`  
- `/crm/` login, **Administration → Manage data** → `/crm/manage_data/`  
- `/Streaming/`  
- `/home/api/temple-identity.php` returns JSON (CRM DB + settings)

## Schema

No manual SQL migration for recent CRM changes: first hit on `/crm/` runs `migrate_schema()` (e.g. `vastra_movements`). See [crm/README.md](../crm/README.md#schema-upgrades-existing-production-dbs).