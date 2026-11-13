# Manage data (Admin only)

Standalone maintenance UI — **not linked** from CRM menus and **no changes** to core CRM PHP views.

## URL

After logging into CRM as **Admin**:

`https://your-site/crm/manage_data/`

Optional: set `MANAGE_DATA_KEY` in `crm/.env` and open:

`https://your-site/crm/manage_data/?key=YOUR_SECRET`

## Access

- Uses the same CRM session cookie (log in at `crm/index.php` first).
- Only users with role **Admin** can use the tool.
- If not logged in: message to log in as Admin.

## Tabs

1. **Database records** — delete by type and date range (or all dates): donations/ledger, online bookings (+ notifications/payments), online donation intents, subscription invoices. Refreshes `storage/install/books.jsonl` when possible.
2. **Receipt PDFs** — list files in `crm/storage/receipts/`, filter, download ZIP, delete PDFs (optionally all matching filter).

**Confirmation:** type `DELETE` before destructive actions.

## Deploy

Upload the whole `crm/manage_data/` folder. No `install.php` step.
