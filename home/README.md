# Public home site (`home/`)

Production serves visitors from **document root** (`index.html` at `public_html/`), not from `/home/`.

Work in this folder, then run:

```powershell
.\scripts\sync-home-to-root.ps1
```

before uploading to hosting (see [docs/DEPLOY-PRODUCTION.md](../docs/DEPLOY-PRODUCTION.md)).

---

## Dynamic content (no React rebuild)

| Feature | Folder / source | Script / API |
|--------|------------------|--------------|
| **Hero carousel** | `photos/hero/` — images + **mp4/webm** | `api/hero-media.php`, `hero-dynamic.js` |
| **Gallery** | `gallery/` (+ subfolders = tabs) | `api/gallery-images.php`, `gallery-dynamic.js` |
| **Identity** (name, phones, email, logo, favicon) | CRM **Settings → Identity** | `api/temple-identity.php`, `identity-dynamic.js` |
| **Booking / donate / live links** | — | `site-links.js` |
| **Temple theme (colours, type)** | — | `home-renovation.css` |

Details for photo folders: [photos/README.md](photos/README.md).

---

## Build output

| Path | Purpose |
|------|---------|
| `index.html` | SPA shell + cache-busted script tags |
| `assets/` | Vite bundle (`index-*.js`, `index-*.css`) |
| `../` (after sync) | Same files copied to repo root for deploy |

---

## Staff workflow

1. Add or replace media under `photos/hero/` or `gallery/`.
2. Run `sync-home-to-root.ps1` before FTP/cPanel upload (or upload both `home/` and synced root files).
3. Update temple contact in **CRM → Settings → Identity** (not in website HTML).
4. Wait ~2 minutes or hard-refresh browsers after API-side changes.

---

## Mobile UX

- **≤767px:** floating **Services** button (donate + book), not the full-width bottom bar.
- **Desktop:** fixed Donate / Book bar at bottom of home.