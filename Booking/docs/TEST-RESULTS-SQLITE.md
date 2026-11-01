# Automated test results (sqlite)

Run: 2026-11-01 18:50 IST · PHP 8.2.27 · SQLite 3.39.2 · mock WhatsApp Cloud API

**60 of 61 tests passed.**

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
| 10 | Multiple services in one booking (Special + Vehicle Puja) | PASS |
| 11 | Service booking with food coupons (4 persons, first category) | PASS |
| 12 | Service booking with food coupons (2 persons, second category) | PASS |
| 13 | Combined coupon categories: two food lines itemised separately | PASS |
| 14 | Food-only booking (no puja) with both categories | PASS |
| 15 | Food-only booking requires a coupon category and persons | PASS |
| 16 | Food-only cannot be combined with another service | PASS |
| 17 | Selected coupon category with 0 persons is rejected | PASS |
| 18 | "Add food" ticked without any category is rejected | PASS |
| 19 | Quantity above the per-booking maximum is rejected | PASS |
| 20 | Tampered price fields from the browser are ignored (server-side pricing) | PASS |
| 21 | Invoice: HTML and PDF contain booking, invoice number and correct totals | PASS |
| 22 | Duplicate submission (double click / resubmit) creates only one booking | PASS |
| 23 | Concurrent submissions get unique, sequential booking numbers | PASS |
| 24 | Price change between review and submit is shown before saving | PASS |
| 25 | Past dates and dates beyond the booking window are rejected | PASS |
| 26 | Blocked (unavailable) dates are rejected with the reason | PASS |
| 27 | Full time slot cannot be overbooked | PASS |
| 28 | Per-day max bookings per service is enforced | PASS |
| 29 | Submitting a UPI reference does NOT mark the booking paid | PASS |
| 30 | Invalid and reused UPI references are rejected | PASS |
| 31 | Admin verifies payment → paid and confirmed, with audit trail | PASS |
| 32 | Amount mismatch needs an explanatory note | PASS |
| 33 | Admin rejects a payment → devotee can resubmit | PASS |
| 34 | Ceremony needing approval: paid ≠ confirmed until approved | PASS |
| 35 | Reschedule moves date and slot, checks capacity, is audited | PASS |
| 36 | Cancel then refund a paid booking | PASS |
| 37 | Other Temple Services: free request, admin sets charges, devotee then pays | PASS |
| 38 | Cancelled bookings free their slot | PASS |
| 39 | Not configured: booking saved, failure logged for follow-up | PASS |
| 40 | Enabled: devotee + admin receive template messages; status "sent" | PASS |
| 41 | Provider rejection: booking still saved, error recorded, no retry for permanent errors | PASS |
| 42 | Temporary provider failure is scheduled for automatic retry | PASS |
| 43 | Status-change messages are sent (payment verified) | PASS |
| 44 | Delivery webhook: valid signature updates status; invalid is refused | PASS |
| 45 | Dashboard pages require login | PASS |
| 46 | Staff cannot open senior-admin pages | PASS |
| 47 | Wrong passwords lock the account after 5 attempts | PASS |
| 48 | Forms without a valid CSRF token are refused | PASS |
| 49 | Bookings cannot be opened without the private link or lookup | PASS |
| 50 | Script injection is rejected in names and escaped everywhere else | PASS |
| 51 | SQL-injection style input is treated as plain text | PASS |
| 52 | Array / malformed parameters do not crash the app | PASS |
| 53 | Booking lookup is rate limited | PASS |
| 54 | Security headers are sent | PASS |
| 55 | CSV exports (bookings, items, payments) download with correct totals | PASS |
| 56 | Daily / weekly / monthly summaries match verified payments | PASS |
| 57 | Admin changes prices; new bookings use them, old bookings keep theirs | PASS |
| 58 | Food coupon catalog price drives booking totals | PASS |
| 59 | Disabled services disappear from booking and are refused | PASS |
| 60 | UPI QR only appears after payment details are verified | FAIL: QR PNG download |
| 61 | Data persists: bookings, items, payments and audit are in the database | PASS |
