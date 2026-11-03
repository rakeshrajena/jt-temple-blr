# Automated test results (sqlite)

Run: 2026-11-03 23:48 IST · PHP 8.2.27 · SQLite 3.39.2 · mock WhatsApp Cloud API

**67 of 67 tests passed.**

| # | Test | Result |
|---|------|--------|
| 1 | Money formatting uses Indian grouping | PASS |
| 2 | Mobile number normalisation | PASS |
| 3 | Financial year for invoice numbers | PASS |
| 4 | Status rules: confirmed only when paid and approved | PASS |
| 5 | UPI reference validation | PASS |
| 6 | CSV export neutralises spreadsheet formulas | PASS |
| 7 | WhatsApp template parameters are sanitised | PASS |
| 8 | Payment gateway signatures (Razorpay) are verified with HMAC | PASS |
| 9 | Individual service booking (Special Puja) — booking number, totals, pending status | PASS |
| 10 | Only one service per booking (two puja services rejected) | PASS |
| 11 | Service booking with food coupons (4 persons, first category) | PASS |
| 12 | Service booking with food coupons (2 persons, second category) | PASS |
| 13 | Combined coupon categories: two food lines itemised separately | PASS |
| 14 | Food-only booking (no puja) with both categories | PASS |
| 15 | Food-only booking requires a coupon category and persons | PASS |
| 16 | Food-only cannot be combined with another service | PASS |
| 17 | Selected coupon category with 0 persons is rejected | PASS |
| 18 | "Add food" ticked without any category is rejected | PASS |
| 19 | Quantity above the per-booking maximum is rejected | PASS |
| 20 | Daily maximum for a prasadam item is shared across bookings on that date | PASS |
| 21 | Tampered price fields from the browser are ignored (server-side pricing) | PASS |
| 22 | Review and receipt show service date and chosen time slots | PASS |
| 23 | Pay later link on payment page follows admin setting | PASS |
| 24 | Invoice: HTML and PDF contain booking, invoice number and correct totals | PASS |
| 25 | Duplicate submission (double click / resubmit) creates only one booking | PASS |
| 26 | Concurrent submissions get unique, sequential booking numbers | PASS |
| 27 | Price change between review and submit is shown before saving | PASS |
| 28 | Past dates and dates beyond the booking window are rejected | PASS |
| 29 | Blocked (unavailable) dates are rejected with the reason | PASS |
| 30 | Full time slot cannot be overbooked | PASS |
| 31 | Per-day max bookings per service is enforced | PASS |
| 32 | Submitting a UPI reference does NOT mark the booking paid | PASS |
| 33 | Invalid and reused UPI references are rejected | PASS |
| 34 | Admin verifies payment → paid and confirmed, with audit trail | PASS |
| 35 | Amount mismatch needs an explanatory note | PASS |
| 36 | Admin rejects a payment → devotee can resubmit | PASS |
| 37 | Ceremony needing approval: paid ≠ confirmed until approved | PASS |
| 38 | Reschedule moves date and slot, checks capacity, is audited | PASS |
| 39 | Reschedule checks prasadam daily max on the new date | PASS |
| 40 | Cancel then refund a paid booking | PASS |
| 41 | Other Temple Services: free request, admin sets charges, devotee then pays | PASS |
| 42 | Cancelled bookings free their slot | PASS |
| 43 | Not configured: booking saved, failure logged for follow-up | PASS |
| 44 | Enabled: devotee + admin receive template messages; status "sent" | PASS |
| 45 | Provider rejection: booking still saved, error recorded, no retry for permanent errors | PASS |
| 46 | Temporary provider failure is scheduled for automatic retry | PASS |
| 47 | Status-change messages are sent (payment verified) | PASS |
| 48 | Delivery webhook: valid signature updates status; invalid is refused | PASS |
| 49 | Dashboard pages require login | PASS |
| 50 | Staff cannot open senior-admin pages | PASS |
| 51 | Wrong passwords lock the account after 5 attempts | PASS |
| 52 | Forms without a valid CSRF token are refused | PASS |
| 53 | Bookings cannot be opened without the private link or lookup | PASS |
| 54 | Script injection is rejected in names and escaped everywhere else | PASS |
| 55 | SQL-injection style input is treated as plain text | PASS |
| 56 | Array / malformed parameters do not crash the app | PASS |
| 57 | Booking lookup is rate limited | PASS |
| 58 | Security headers are sent | PASS |
| 59 | CSV exports (bookings, items, payments) download with correct totals | PASS |
| 60 | Daily / weekly / monthly summaries match verified payments | PASS |
| 61 | Admin changes prices; new bookings use them, old bookings keep theirs | PASS |
| 62 | Food coupon catalog price drives booking totals | PASS |
| 63 | Disabled services disappear from booking and are refused | PASS |
| 64 | UPI QR only appears after payment details are verified | PASS |
| 65 | Book check and review-check enforce availability before submit | PASS |
| 66 | Admin day list defaults to today and cancels one service or prasadam line | PASS |
| 67 | Data persists: bookings, items, payments and audit are in the database | PASS |
