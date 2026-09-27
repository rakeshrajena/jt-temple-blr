# Features

This list is for the PHP app in `php/`, which is the one served by AMPPS. The Flask app in `python/` is the earlier copy and does not include every item below.

Nothing in the “To add” section is built yet.

## We have

### Sign-in and people

- Admin and Staff accounts. Passwords are hashed.
- Staff can use every module except Users.
- An Admin adds or deactivates accounts. At most 10 active users. The last active Admin cannot be deactivated.
- An Admin opens Settings to save the outgoing mail server and the WhatsApp Web message. The mail password is kept and is not shown again.
- App contributors is a card list in Administration: name, photo, contact, email, location, designation, and a public profile link. Everyone who is signed in can read the cards. Only an Admin can add, update, or remove a person.
- Every screen shows ©, the temple name, and the current year. On the registers that line is centered.
- Long tables without their own filters (6 rows or more) get a Filter this table box that hides rows not matching the typed words and shows the count. Tables with selection checkboxes are left out so a bulk action never includes hidden rows. A table can opt out with `data-no-filter` or opt in with `data-filter`. The code is in `php/static/js/table_filter.js`.
- Form instructions sit behind a **?** icon beside the heading or field they explain. Pointing at it, focusing it with the keyboard, or tapping it shows the text one sentence per line. A click keeps it open until you click elsewhere or press Escape. Live status lines, such as counts and errors, stay visible.

### Server install

- `install.php` first checks the server: PHP 8.1 or newer, the extensions `pdo_mysql`, `mbstring`, `zlib`, `json`, `openssl`, `simplexml`, and `dom`, and writable `storage` folders. It lists each problem with what to change in cPanel and will not run setup until they are fixed. After loading the saved books, it makes any receipt PDF that did not upload.
- `install.php` needs the setup key from the server `.env`. It adds every missing table and column, then loads the saved books when the database has no devotees or when replace is ticked. The page shows how many rows the saved copy holds and when it was saved.
- `bin/export-books.php` saves every table to `storage/install/books.jsonl`. `bin/verify-install.php` builds a temporary database from `schema.sql` and that file, compares every table, column, and row count with the live books, and always removes the temporary database. Tests are in `php/tests/install_check_test.php`.

### Dashboard and overview

- Totals for donations, expenses, net balance, donors, receipts still to generate, low food stock, unmatched bank lines, inventory count, monthly subscription revenue, and invoices due.
- Recent donations, with the receipt number opening the PDF.
- A single overview page of donations, stock, vastra, subscriptions, and bank lines.

### Inventory, food, and vastra

- Inventory items with category, quantity, unit cost, current value, condition, location, and whether the item was purchased or donated.
- Stock movements for issue, return, damage, loss, and retired. Issue and return are recorded immediately. Damage, loss, and retired quantities above 5 units wait for approval, and the quantity stays until then.
- A purchase raises stock and writes the payment in the cash book in one step, only after approval. The rate on hand becomes the weighted average of the old stock and the new purchase.
- Condition and location can be changed after the item is added. Needs Repair marks a repair. A new location moves it. Retired writes off the quantity still on hand, and that write-off waits when it is above 5 units.
- Food stock with a minimum level, a low-stock flag, and a log of stock added or used. Kitchen use of 5 units or less is a normal log. Above that it waits for approval.
- Coupons use one generate form. Quantity 1 is issued at once, without approval, and can be printed immediately. A quantity above 1 waits for approval. The coupon name and the purpose are chosen from the Puja purpose list in Settings. Choosing a coupon name fills the amount. Choosing a purpose does not. An Admin can change those amounts or remove a purpose. The list is stored in `php/storage/selections.json`.
- Food coupon batches with one stored coupon per serial, a printable PDF, and an optional expiry. The face value waits for approval and does not enter the books. The QR code is a link to that coupon. A phone scan checks it first, then records the donation if it is still valid. Signing in is required, and the sign-in page returns to that coupon. Typing a sold coupon does the same and posts that income to the cash or bank book. A coupon past its expiry is invalidated automatically. Removing a batch deletes its unused coupons. A sold coupon stays in the books. A JSON API can validate, invalidate, or read a code.
- Deity vastra by deity, colour, quantity, source, and status: In Store, In Use, or Retired.

### Donations and receipts

- Cash and in-kind donations. In-kind food, vastra, and inventory update those registers.
- Donor name, phone, email, address, and PAN. A repeat phone matches the existing donor.
- Payment modes: Cash, UPI, bank transfer, cheque, card, netbanking, and in-kind.
- A PDF receipt (`RCPT-YYYY-NNNN`). The receipt number is a link on Donations, the dashboard, the overview, the donation report, and a matched bank line. Generate receipt stays available after a receipt exists, and it writes the current gift over the same number. A cancelled receipt is left as it is.
- Edit on a gift changes the devotee, amount, purpose, date, or payment only after approval. Within 24 hours of when the gift was added, a Treasurer can approve it up to the Treasurer limit, and an Admin decides above that. After 24 hours, both a Treasurer and an Admin must approve any change. The person who submitted the edit cannot approve it. Approving an edit rewrites the receipt when one already exists.
- Every value on the receipt PDF wraps inside the gold border. A crowded receipt uses smaller text before anything is shortened, and the text never reaches the signature or QR code. Dashes, quotes, and the rupee sign print as plain text. Tests are in `php/tests/receipt_layout_test.php`.
- A Receipts page lists every generated PDF. Anyone signed in can open one. An Admin can download a zip of many receipts. Cancelling a receipt keeps the number and the PDF and waits for approval.

### Subscriptions

- Recurring seva plans and invoices.
- A subscriber has a name, contact number, optional email, family members, gotra, special date for seva, plan, amount, billing cycle, and status.
- Plan, billing cycle, and status suggest names as you type. Each is a list under Settings, Form choices. Active cannot be removed.
- Statuses start as Active, Paused, Inactive, and Cancelled. Only Active gets a new invoice, and the server enforces this.
- Update on each row edits every field. A status change is kept in `subscriber_status_log` with the old status, the new one, the user, and the time, and the latest change shows on the row.
- + Invoice creates this month's invoice and emails the subscriber a payment request: plan, billing cycle, period, amount, due date, invoice number, and a Pay or donate button to the payment link. An unpaid invoice for the same month is emailed again instead of being duplicated. Without an email address or SMTP, the invoice is created and the page says nothing was emailed. A refused email leaves the invoice Pending. Tests are in `php/tests/subscriber_test.php`.
- The invoice list filters by name, phone, email, or invoice number; status; period; due date range; and receipt made or missing. Unknown values in the address are ignored, and search text is bound as a parameter. Tests are in `php/tests/invoice_filter_test.php`.
- Send writes the message to a log and emails the same payment request when SMTP is saved under Settings. WhatsApp Web opens with the message filled in. There is no WhatsApp API. The devotee still confirms payment on the link.
- The devotee opens a payment link without signing in. Confirming payment marks the invoice Paid and copies it into Donations.
- A paid invoice gets its receipt at once, through the same code as a donation receipt: next `RCPT-` number, same PDF, public link, and Receipts page entry. The thank-you page links to it. On the invoice row, the receipt number opens the PDF, Send receipt emails it to the subscriber, and Generate or Update receipt makes or rewrites it. Tests are in `php/tests/subscription_receipt_test.php`.

### Expenses and bank

- Expenses by category, paid-to, date, and payment mode, with a free-text bill reference. A new expense waits for approval before it enters the books.
- Upload a CSV or Excel bank statement. A credit matches a donation and a debit matches an approved expense. A UPI id or cheque number in the narration matches within 90 days; otherwise the same amount matches within 3 days. Anything left over can be linked by hand.

### Reports

- Printable reports for donations, expenses, inventory, food, vastra, and bank reconciliation. Donation and expense reports can be filtered by date.

### Account book

- Cash book for one financial year (April to March), with receipt and payment columns for cash and for bank, plus opening and closing balances.
- Movements before the chosen start date, but inside the same financial year, are folded into the opening balance.
- Cash donations and cash expenses change cash. UPI, bank transfer, cheque, card, and netbanking change the bank. In-kind gifts stay out of the book.
- Contra entries: cash deposited in the bank, or cash withdrawn. Both balances move, and the combined total does not.
- A Treasurer or Admin submits the opening cash and bank balance for the year. It changes the books only after someone else approves it. Until then, the year opens at zero.
- Carry forward copies one year’s closing cash and bank into the next year’s opening. It uses the whole year, through 31 March, and it waits for approval. The closing on a shorter date range is not what gets copied. A negative closing is refused.
- Day book: the same period as one date-wise list, with who entered each line.
- Ledger by head for the financial year. A donation purpose and an expense category are each a head. Received money and spending are shown, with the balance. The same name shares one fund. In-kind gifts stay out.
- Each expense gets a voucher number for its financial year (`VCH-YYYY-NNNN`). The bill can be noted or attached as a PDF or image. The voucher shows on the expense list, the cash book, and the ledger.
- Approval for expenses, cash deposits and withdrawals, opening-balance changes, purchases, stock write-offs, corrections, receipt cancellations, donation edits, and coupon batches. Staff prepare an item. A Treasurer can approve up to ₹10,000. Above that, an Admin decides. The person who prepared it cannot approve it. A donation edit from the last 24 hours follows that limit. An older donation edit waits until both a Treasurer and an Admin approve it. States are Draft, Waiting, Approved, Sent back, and Rejected. Only an Approved line enters the cash book, day book, ledger, and bank match. A waiting purchase does not change stock. A donation that has just been received does not wait.
- A UPI payment stores the transaction id. A cheque stores its number, date, and whether it has cleared. The bank match uses that reference in the narration, up to 90 days, before it falls back to the same amount within 3 days.
- A correction keeps the original donation or expense and adds a second line with a reason. It changes the cash book, day book, and ledger only after approval. Voiding a line reverses the whole amount. Setting a new amount posts only the difference.
- Cancelling a receipt keeps the number and the PDF. The donation page and the receipts list show it as cancelled after approval. The money stays in the books. Deleting a receipt file is no longer the way to undo one.
- Donor ledger. One page per devotee for the financial year: every gift, the receipt number, PAN, and the total received. In-kind gifts are listed and are not added to that total.
- A pledge is a promise. It shows promised, received, and still to come. Only the amount received enters the cash book. The page prints as the yearly statement.

## Build order

Each step is finished, with tests, before the next one starts. A Waiting line does not change a balance. A donation that has just been received still posts immediately.

1. **Cash book, contra entries, day book, financial year, opening balance.** Done. Tests are in `php/tests/books_test.php`.
2. **Ledger by head.** Done. Tests are in `php/tests/books_test.php`.
3. **Expense voucher number, cheque number, and UPI reference.** Done. Tests are in `php/tests/vouchers_test.php`.
4. **Approval hierarchy for expenses, contra entries, and opening balance.** Done. Tests are in `php/tests/approval_test.php`. Purchases and stock write-off use the same chain.
5. **Corrections that keep the original line, and receipt cancellation that stays on the donor page.** Done. Tests are in `php/tests/corrections_test.php`.
6. **Donor ledger, yearly statement, and pledge versus received.** Done. Tests are in `php/tests/donors_test.php`.
7. **Inventory movement, purchase posted with its payment, condition and location changes, and stock value.** Done. Tests are in `php/tests/stock_test.php`. A write-off above 5 units waits for approval.
8. **Carry one year’s closing cash and bank into the next opening.** Done. Tests are in `php/tests/carry_test.php`. It waits for approval.
9. QR scan from the page camera.

## To add

### Later

- **QR scan from the page camera.** The browser reads the code and sends the text to PHP, for a receipt number or a devotee. PHP cannot open the camera by itself.
