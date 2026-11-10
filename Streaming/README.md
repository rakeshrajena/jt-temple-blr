# Live darshan streaming

Reads one stream URL from `.streaming` and embeds it, or shows a multilingual “not available” message.

## Staff: set stream URL

1. Copy `.env.example` → `.env` in this folder on the server.
2. Set `STREAM_SAVE_TOKEN` to a long random secret.
3. Open Live darshan → gear → paste URL → enter the same secret → **Save**.

Do not commit `.env` or `.streaming` (see repo `.gitignore`).

## Deploy

Upload `Streaming/` to `public_html/Streaming`. Ensure `.streaming` and `.env` exist only on the server.

## License

GPL-3.0 (see [`../LICENSE`](../LICENSE)).
