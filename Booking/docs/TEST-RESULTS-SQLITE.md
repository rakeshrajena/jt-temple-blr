# Automated test results (sqlite)

Run: 2026-10-10 14:44 IST · PHP 8.3.6 · SQLite 3.45.1 · mock WhatsApp Cloud API

**60 of 60 tests passed.**

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
| 11 | Service booking with ₹150 food coupons (4 persons) | PASS |
| 12 | Service booking with ₹250 food coupons (2 persons) | PASS |
| 13 | Combined coupon categories: 150×4 + 250×2 = ₹1,100 food, itemised separately | PASS |
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
| 28 | Submitting a UPI reference does NOT mark the booking paid | PASS |
| 29 | Invalid and reused UPI references are rejected | PASS |
| 30 | Admin verifies payment → paid and confirmed, with audit trail | PASS |
| 31 | Amount mismatch needs an explanatory note | PASS |
| 32 | Admin rejects a payment → devotee can resubmit | PASS |
| 33 | Ceremony needing approval: paid ≠ confirmed until approved | PASS |
| 34 | Reschedule moves date and slot, checks capacity, is audited | PASS |
| 35 | Cancel then refund a paid booking | PASS |
| 36 | Other Temple Services: free request, admin sets charges, devotee then pays | PASS |
| 37 | Cancelled bookings free their slot | PASS |
| 38 | Not configured: booking saved, failure logged for follow-up | PASS |
| 39 | Enabled: devotee + admin receive template messages; status "sent" | PASS |
| 40 | Provider rejection: booking still saved, error recorded, no retry for permanent errors | PASS |
| 41 | Temporary provider failure is scheduled for automatic retry | PASS |
| 42 | Status-change messages are sent (payment verified) | PASS |
| 43 | Delivery webhook: valid signature updates status; invalid is refused | PASS |
| 44 | Dashboard pages require login | PASS |
| 45 | Staff cannot open senior-admin pages | PASS |
| 46 | Wrong passwords lock the account after 5 attempts | PASS |
| 47 | Forms without a valid CSRF token are refused | PASS |
| 48 | Bookings cannot be opened without the private link or lookup | PASS |
| 49 | Script injection is rejected in names and escaped everywhere else | PASS |
| 50 | SQL-injection style input is treated as plain text | PASS |
| 51 | Array / malformed parameters do not crash the app | PASS |
| 52 | Booking lookup is rate limited | PASS |
| 53 | Security headers are sent | PASS |
| 54 | CSV exports (bookings, items, payments) download with correct totals | PASS |
| 55 | Daily / weekly / monthly summaries match verified payments | PASS |
| 56 | Admin changes prices; new bookings use them, old bookings keep theirs | PASS |
| 57 | Admin changes food coupon price | PASS |
| 58 | Disabled services disappear from booking and are refused | PASS |
| 59 | UPI QR only appears after payment details are verified | PASS |
| 60 | Data persists: bookings, items, payments and audit are in the database | PASS |
