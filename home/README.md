# Home site (Vite build output)

Production serves this content from **document root** (`public_html/`), not from `/home/`.

## Photo layout (home page only)

| Path | Purpose |
|------|---------|
| `home/photos/branding/` | Logo |
| `home/photos/hero/` | Homepage banner |
| `home/photos/temple/` | Temple images |
| `home/photos/events/` | Festival cards |
| `home/gallery/` | **Auto gallery** — add images here; newest appear on `/gallery` and home highlights |

API: `home/api/gallery-images.php` (JSON list, sorted by file date).

**Identity (name, address, phone, email, favicon/logo)** comes from CRM **Settings → Identity** via `home/api/temple-identity.php`, same source as Booking and Donation.

See `photos/README.md` for staff notes.

## Deploy

1. Organize assets: `.\scripts\organize-home-photos.ps1`
2. Sync to repo root: `.\scripts\sync-home-to-root.ps1`
3. Upload root files to server (include `home/photos/`, `home/gallery/`, `home/api/`).

Root `.htaccess` serves `home/photos/`, `home/gallery/`, and `home/api/` without redirecting to legacy root paths.