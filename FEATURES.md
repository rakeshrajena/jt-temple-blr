# Features

This list is for the PHP app in `php/`, which is the one served by AMPPS. The Flask app in `python/` is the earlier copy and does not include every item below.

Nothing in the “To add” section is built yet.

## We have

### Sign-in and people

- Admin and Staff accounts. Passwords are hashed.
- Staff can use every module except Users.
- An Admin adds or deactivates accounts. At most 10 active users. The last active Admin cannot be deactivated.
- Forms require a session token.

### Dashboard and overview

- Totals for donations, expenses, net balance, donors, receipts still to generate, low food stock, unmatched bank lines, inventory count, monthly subscription revenue, and invoices due.
- Recent donations, with the receipt number opening the PDF.
- A single overview page of donations, stock, vastra, subscriptions, and bank lines.

### Inventory, food, and vastra

- Inventory items with category, quantity, unit, condition, location, and whether the item was purchased or donated.
- Food stock with a minimum level, a low-stock flag, and a log of stock added or used.
- Food coupon batches with sequential numbers and a printable PDF.
- Deity vastra by deity, colour, quantity, source, and status: In Store, In Use, or Retired.

### Donations and receipts

- Cash and in-kind donations. In-kind food, vastra, and inventory update those registers.
- Donor name, phone, email, address, and PAN. A repeat phone matches the existing donor.
- Payment modes: Cash, UPI, bank transfer, cheque, card, netbanking, and in-kind.
- A PDF receipt (`RCPT-YYYY-NNNN`). The receipt number is a link on Donations, the dashboard, the overview, the donation report, and a matched bank line.
- A Receipts page lists every generated PDF. Anyone signed in can open one. An Admin can download a zip of many receipts, or delete selected receipts. Deleting a receipt clears it on the donation so a new one can be generated. The donation itself stays.

### Subscriptions

- Recurring seva plans and invoices.
- Send writes the SMS and email text to a log. Nothing is sent to a phone or inbox.
- The devotee opens a payment link without signing in. Confirming payment marks the invoice Paid and copies it into Donations.

### Expenses and bank

- Expenses by category, paid-to, date, and payment mode, with a free-text bill reference. An expense is final as soon as it is saved. There is no approval step.
- Upload a CSV or Excel bank statement. A credit matches a donation and a debit matches an expense when the amount is the same and the date is within 3 days. Anything left over can be linked by hand.

### Reports

- Printable reports for donations, expenses, inventory, food, vastra, and bank reconciliation. Donation and expense reports can be filtered by date.

### Account book

- Cash book for one financial year (April to March), with receipt and payment columns for cash and for bank, plus opening and closing balances.
- Movements before the chosen start date, but inside the same financial year, are folded into the opening balance.
- Cash donations and cash expenses change cash. UPI, bank transfer, cheque, card, and netbanking change the bank. In-kind gifts stay out of the book.
- Contra entries: cash deposited in the bank, or cash withdrawn. Both balances move, and the combined total does not.
- An Admin sets the opening cash and bank balance for the year. Until that is saved, the year opens at zero.
- Day book: the same period as one date-wise list, with who entered each line.
- Ledger by head for the financial year. A donation purpose and an expense category are each a head. Received money and spending are shown, with the balance. The same name shares one fund. In-kind gifts stay out.
- Each expense gets a voucher number for its financial year (`VCH-YYYY-NNNN`). The bill can be noted or attached as a PDF or image. The voucher shows on the expense list, the cash book, and the ledger.
- Approval for expenses, cash deposits and withdrawals, and opening-balance changes. Staff prepare an item. A Treasurer can approve up to ₹10,000. Above that, an Admin decides. The person who prepared it cannot approve it. States are Draft, Waiting, Approved, Sent back, and Rejected. Only an Approved line enters the cash book, day book, ledger, and bank match. A donation that has just been received does not wait. Demo login: `treasurer` / `treasurer@123`.
- A UPI payment stores the transaction id. A cheque stores its number, date, and whether it has cleared. The bank match uses that reference in the narration, up to 90 days, before it falls back to the same amount within 3 days.

## Build order

Each step is finished, with tests, before the next one starts. A Waiting line does not change a balance. A donation that has just been received still posts immediately.

1. **Cash book, contra entries, day book, financial year, opening balance.** Done. Tests are in `php/tests/books_test.php`.
2. **Ledger by head.** Done. Tests are in `php/tests/books_test.php`.
3. **Expense voucher number, cheque number, and UPI reference.** Done. Tests are in `php/tests/vouchers_test.php`.
4. **Approval hierarchy for expenses, contra entries, and opening balance.** Done. Tests are in `php/tests/approval_test.php`. Purchases, corrections, receipt cancellation, and stock write-off will use the same chain when steps 5 and 7 are built.
5. Corrections that keep the original line, and receipt cancellation that stays on the donor page.
6. Donor ledger and the yearly donor statement. Pledge versus amount received.
7. Inventory movement, purchase posted with its payment, condition and location changes, and stock value.
8. QR scan from the page camera.

## To add

### Account book

- **Correct a wrong entry.** Keep the original line and add a correction with a reason. Donations and expenses can only be added today.
- **Carry forward.** Copy one year’s closing cash and bank into the next year’s opening balance. The year boundaries are already in place, and an Admin can type the opening by hand.
- **Donor ledger.** One page per devotee: every gift, the receipt number, and the yearly total. PAN is already stored, so this is also the yearly statement for devotees who ask.

### Approval still to connect

The chain is in place for expenses, contra entries, and opening balances. These later actions will use the same approval record when they are built:

- **A purchase that also adds stock**, because it spends money and changes the store. Step 7.
- **A correction or void** of a donation or expense that is already in the book. Step 5.
- **Cancelling a receipt** after it has been generated. Step 5.
- **Stock written off** as damage, loss, or retired. Daily food used for the kitchen can stay a normal stock log when it is under a set quantity. Above that quantity it waits for approval. Step 7.

### Inventory

- **Stock movement for general inventory.** Food already logs Added and Used. Hardware, puja items, and furniture only store a quantity. Issue, return, damage, and loss need their own lines.
- **Purchase and payment together.** Marking an item Purchased does not create an expense. A purchase should raise the stock and write the payment in the cash book in one step.
- **Condition and location after the item is added.** Repair, move to another room, or retire an item.
- **Stock value.** Quantity is stored. Purchase rate and current value are not.

### Donations

- **Cancel a receipt and keep the history.** Deleting a receipt today only removes the PDF. A cancelled receipt should stay on the donor’s page as cancelled.
- **Yearly donor statement**, printable, with receipt numbers and PAN.
- **Pledge versus received.** A promised amount and the amount actually received are different lines. Only the received amount exists now.

### Later

- **QR scan from the page camera.** The browser reads the code and sends the text to PHP, for a receipt number or a devotee. PHP cannot open the camera by itself.
