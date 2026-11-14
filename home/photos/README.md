# Home page photos (not CRM / Booking / Streaming)

| Folder | Use |
|--------|-----|
| `branding/` | Logo, favicon source (`logo.jpg`) |
| `hero/` | **Hero slideshow** — drop images (jpg, png, webp, gif, avif, …) and videos (**mp4**, **webm** only). Auto-rotates on the home page; videos play muted then advance. |
| `temple/` | Temple architecture / deity images |
| `events/` | Festival and event cards on the home page |

## Gallery (automatic)

Put full-size gallery images in **`../gallery/`** (sibling folder, not here).

- Supported: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif`
- Files are listed **newest first** on the home “Gallery Highlights” and `/gallery` page.
- No code changes needed when adding images.
- Filenames with `phone`, `screenshot`, or `thumb` in the name are ignored.

After changes, run `scripts/sync-home-to-root.ps1` before deploying to production document root.