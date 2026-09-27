# Features

This list is for the PHP app in `php/`, which is the one served by AMPPS. The Flask app in `python/` is the earlier copy and does not include every item below.

Nothing in the “To add” section is built yet.

## We have

### Sign-in and people

- Admin and Staff accounts. Passwords are hashed.
- Staff can use every module except Users.
- An Admin adds or deactivates accounts. At most 10 active users. The last active Admin cannot be deactivated.
- An Admin opens Settings to save the outgoing mail server and the WhatsApp Web message. The mail password is kept and is not shown again.

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
- Food coupon batches with one stored coupon per serial, a printable PDF, and an optional expiry. The face value waits for approval and does not enter the books. The QR code is a link to that coupon. A phone scan checks it first, then records the donation if it is still valid. Signing in is required, and the sign-in page returns to that coupon. Typing a sold coupon does the same and posts that income to the cash or bank book. A coupon past its expiry is invalidated automatically. Removing a batch deletes its unused coupons. A sold coupon stays in the books. A JSON API can validate, invalidate, or read a code.
- Deity vastra by deity, colour, quantity, source, and status: In Store, In Use, or Retired.

### Donations and receipts

- Cash and in-kind donations. In-kind food, vastra, and inventory update those registers.
- Donor name, phone, email, address, and PAN. A repeat phone matches the existing donor.
- Payment modes: Cash, UPI, bank transfer, cheque, card, netbanking, and in-kind.
- A PDF receipt (`RCPT-YYYY-NNNN`). The receipt number is a link on Donations, the dashboard, the overview, the donation report, and a matched bank line.
- A Receipts page lists every generated PDF. Anyone signed in can open one. An Admin can download a zip of many receipts. Cancelling a receipt keeps the number and the PDF and waits for approval.

### Subscriptions

- Recurring seva plans and invoices.
- Send writes the message to a log. When SMTP is saved under Settings, the same message is emailed. WhatsApp Web opens with the message filled in. There is no WhatsApp API. The devotee still confirms payment on the link.
- The devotee opens a payment link without signing in. Confirming payment marks the invoice Paid and copies it into Donations.

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
- Approval for expenses, cash deposits and withdrawals, opening-balance changes, purchases, and stock write-offs. Staff prepare an item. A Treasurer can approve up to ₹10,000. Above that, an Admin decides. The person who prepared it cannot approve it. States are Draft, Waiting, Approved, Sent back, and Rejected. Only an Approved line enters the cash book, day book, ledger, and bank match. A waiting purchase does not change stock. A donation that has just been received does not wait. Demo login: `treasurer` / `treasurer@123`.
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
