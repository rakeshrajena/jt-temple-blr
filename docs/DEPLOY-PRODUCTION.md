# Production deployment (hosting / cPanel)

Deploy the **full stack** under one document root (e.g. `public_html/` for `https://sjtbengaluru.org/`).

## Upload to hosting root (important)

1. On your PC, run:

   ```powershell
   .\scripts\pre-deploy.ps1
   ```

   This syncs `home/` → repo root and runs `verify-deploy-links.ps1`.

2. Upload **everything inside** `jt-temple-blr/` into the host’s **document root** (`public_html/`), keeping the same folder names:

   - `index.html` must sit at `public_html/index.html` (not `public_html/jt-temple-blr/index.html`).
   - Sibling apps: `public_html/Booking/`, `public_html/crm/`, `public_html/Streaming/`, `public_html/home/`.

3. **Do not** change link paths in the React bundle for production. The first script in `index.html` sets:

   - `SJT_PUBLIC_BASE` from the current URL (works at **domain root** and in a **subfolder** on AMPPS).
   - `SJT_BOOKING_URL`, `SJT_DONATION_URL`, `SJT_LIVE_DARSHAN_URL`, and gallery/hero/identity APIs from that base.
   - React Router uses `window.SJT_ROUTER_BASENAME` the same way.

4. `site-links.js` fixes in-page links (`/#about` → `#about`) and wires Booking / Donation / Streaming from the globals above.

## Layout on the server

```
public_html/
  index.html              ← Public home (React SPA shell + temple scripts)
  .htaccess               ← Root rewrite rules (gallery route, home/api)
  assets/                   ← Built JS/CSS from Vite
  site-links.js / .css      ← Booking links, services cards, mobile FAB
  hero-dynamic.js / .css    ← Dynamic hero carousel (photos + mp4/webm)
  gallery-dynamic.js / .css ← Dynamic gallery + lightbox
  identity-dynamic.js       ← Name, contact, logo from CRM settings
  home-renovation.js / .css ← Site-wide temple theme
  robots.txt, sitemap.xml, favicon.ico
  home/
    .htaccess               ← Allow photos/, gallery/, api/
    photos/                 ← Branding, hero, temple, events images
    gallery/                ← Auto gallery (+ subfolders = tabs)
    api/                    ← gallery-images, hero-media, temple-identity PHP
  Booking/                  ← Online booking + donation (see Booking/docs/)
  crm/                      ← Staff ERP (see crm/README.md)
  Streaming/                ← Live darshan page (see Streaming/README.md)
```

**Do not** upload `.env` files, `storage/logs`, session files, or local uploads to a public git mirror.

---

## Before you upload (on your PC)

1. **Sync home → root** (copies latest `home/index.html`, scripts, assets, photos/gallery/api):

   ```powershell
   .\scripts\sync-home-to-root.ps1
   ```

2. **Optional:** organise dropped-in images:

   ```powershell
   .\scripts\organize-home-photos.ps1
   ```

3. Confirm **PHP 8.1+** and **MySQL** on the host (Booking + CRM + home APIs).

---

## Upload checklist

| Step | Action |
|------|--------|
| 1 | Upload entire project (or at minimum: root home files, `home/`, `Booking/`, `crm/`, `Streaming/`) — **flat under public_html** |
| 2 | **Booking:** copy `Booking/.env.example` → `Booking/.env`; set `APP_URL=https://sjtbengaluru.org/Booking` (no trailing slash), `APP_KEY`, `DB_*`, payment/WhatsApp |
| 3 | **CRM:** copy `crm/env.example` → `crm/.env`; `APP_URL=https://sjtbengaluru.org/crm`, `BOOKING_APP_URL=https://sjtbengaluru.org/Booking`; same database as Booking |
| 4 | Run **install/migrate** once on server (`crm/install.php`, `Booking/install.php` or CLI migrate). **Existing CRM DB:** uploading new `crm/` code is enough — the next CRM page load runs `migrate_schema()` (e.g. creates `vastra_movements` if missing). No separate SQL migration file; no automatic backfill of movement history. Optional: `php crm/bin/preflight_deploy.php` |
| 5 | **CRM → Settings → Identity:** temple name, address, phones, email, logo (drives home page + favicon) |
| 6 | **Streaming:** `Streaming/.streaming` with live `https://…` URL (see `Streaming/README.md`) |
| 7 | Set folder permissions: `Booking/storage/*`, `crm/storage/*` writable by PHP |
| 8 | **Delete or block** `install.php` on CRM and Booking after go-live |
| 9 | Remove `INSTALL_TOKEN` from production `.env` |
| 10 | Hard-refresh home page after deploy (`Ctrl+Shift+R`) to load new `?v=` script versions |

---

## Public home (no rebuild for content)

| Content | Where to change |
|---------|-----------------|
| Hero slideshow | Files in `home/photos/hero/` (jpg, png, webp, **mp4**, **webm**) |
| Gallery | `home/gallery/` and subfolders (each non-empty folder = tab) |
| Temple logo / contact / title | CRM **Settings → Identity** (API `home/api/temple-identity.php`) |
| Hero / events / about images | `home/photos/temple/`, `events/`, etc. |

APIs cache responses for ~2 minutes.

---

## Apache / `.htaccess`

Root `.htaccess` must stay enabled:

- Serves `home/photos/`, `home/gallery/`, `home/api/` without breaking paths
- `/gallery` → `index.html` (client-side route)

If the site lives in a **subfolder** (e.g. `/jt_blr/.../`), the inline script in `index.html` sets `SJT_PUBLIC_BASE` automatically.

---

## SEO

- Update `sitemap.xml` and `index.html` canonical/`og:url` if the domain is not `sjtbengaluru.org`
- `robots.txt` already points to the sitemap URL

---

## Post go-live smoke test

- [ ] `https://your-domain/` — hero slides, identity in header/footer
- [ ] `https://your-domain/gallery` — images, folder tabs, modal
- [ ] `https://your-domain/Booking/book` — service list loads
- [ ] `https://your-domain/Booking/donation/` — donation form
- [ ] `https://your-domain/Booking/lookup` — lookup form
- [ ] `https://your-domain/Streaming/` — player or “no stream” message
- [ ] `https://your-domain/crm/` — staff login
- [ ] Network tab: **one** call to `temple-identity.php` per page load (not a loop)

---

## Related docs

- [CRM install & schema upgrades](../crm/README.md#schema-upgrades-existing-production-dbs)
- [Booking hosting (cPanel)](../Booking/docs/DEPLOYMENT-CPANEL.md)
- [Booking pre-launch](../Booking/docs/TEMPLE-ACTION-ITEMS.md)
- [Home folder staff notes](../home/README.md)
- [Photo folders](../home/photos/README.md)