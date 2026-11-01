# Automated test results (sqlite)

Run: 2026-11-01 19:23 IST · PHP 8.2.27 · SQLite 3.39.2 · mock WhatsApp Cloud API

**10 of 61 tests passed.**

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
| 9 | Individual service booking (Special Puja) — booking number, totals, pending status | FAIL: review page — expected 200, got 500 |
| 10 | Multiple services in one booking (Special + Vehicle Puja) | FAIL: combined total — expected 75200, got 0 |
| 11 | Service booking with food coupons (4 persons, first category) | FAIL: total = service + food×4 — expected 110500, got 0 |
| 12 | Service booking with food coupons (2 persons, second category) | FAIL: total = service + food×2 — expected 88300, got 0 |
| 13 | Combined coupon categories: two food lines itemised separately | FAIL: review shows food charges |
| 14 | Food-only booking (no puja) with both categories | FAIL: created — expected 303, got 0 |
| 15 | Food-only booking requires a coupon category and persons | FAIL: rejected — expected 422, got 500 |
| 16 | Food-only cannot be combined with another service | FAIL: rejected — expected 422, got 500 |
| 17 | Selected coupon category with 0 persons is rejected | FAIL: rejected — expected 422, got 500 |
| 18 | "Add food" ticked without any category is rejected | FAIL: rejected — expected 422, got 500 |
| 19 | Quantity above the per-booking maximum is rejected | FAIL: rejected — expected 422, got 500 |
| 20 | Tampered price fields from the browser are ignored (server-side pricing) | FAIL: server price used — expected 50100, got 0 |
| 21 | Invoice: HTML and PDF contain booking, invoice number and correct totals | FAIL: booking(): Argument #1 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 430 |
| 22 | Duplicate submission (double click / resubmit) creates only one booking | FAIL: second submit redirects — expected 303, got 500 |
| 23 | Concurrent submissions get unique, sequential booking numbers | FAIL: all 12 created — expected 12, got 0 |
| 24 | Price change between review and submit is shown before saving | FAIL: review shown again — expected 200, got 500 |
| 25 | Past dates and dates beyond the booking window are rejected | FAIL: past — expected 422, got 500 |
| 26 | Blocked (unavailable) dates are rejected with the reason | FAIL: rejected — expected 422, got 500 |
| 27 | Full time slot cannot be overbooked | FAIL: booking 0 fits capacity 3 — expected 303, got 0 |
| 28 | Per-day max bookings per service is enforced | FAIL: booking 1 under daily cap — expected 303, got 0 |
| 29 | Submitting a UPI reference does NOT mark the booking paid | FAIL: accepted — expected 303, got 500 |
| 30 | Invalid and reused UPI references are rejected | FAIL: booking(): Argument #1 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 555 |
| 31 | Admin verifies payment → paid and confirmed, with audit trail | FAIL: adminAction(): Argument #2 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 562 |
| 32 | Amount mismatch needs an explanatory note | FAIL: adminAction(): Argument #2 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 573 |
| 33 | Admin rejects a payment → devotee can resubmit | FAIL: adminAction(): Argument #2 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 580 |
| 34 | Ceremony needing approval: paid ≠ confirmed until approved | FAIL: approval pending — expected 'pending', got NULL |
| 35 | Reschedule moves date and slot, checks capacity, is audited | FAIL: booking(): Argument #1 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 603 |
| 36 | Cancel then refund a paid booking | FAIL: adminAction(): Argument #2 ($no) must be of type string, null given, called in C:\Program Files\Ampps\www\jt_blr\jt-temple-blr\Booking\tests\run.php on line 624 |
| 37 | Other Temple Services: free request, admin sets charges, devotee then pays | FAIL: no payment due initially — expected 'not_required', got NULL |
| 38 | Cancelled bookings free their slot | FAIL: slot full — expected 422, got 500 |
| 39 | Not configured: booking saved, failure logged for follow-up | FAIL: booking saved |
| 40 | Enabled: devotee + admin receive template messages; status "sent" | FAIL: numbers normalised — expected '+919845011111, +919845022222', got '' |
| 41 | Provider rejection: booking still saved, error recorded, no retry for permanent errors | FAIL: booking saved |
| 42 | Temporary provider failure is scheduled for automatic retry | FAIL: failed — expected 'failed', got NULL |
| 43 | Status-change messages are sent (payment verified) | FAIL: update queued — expected 1, got 0 |
| 44 | Delivery webhook: valid signature updates status; invalid is refused | FAIL: bad signature refused — expected 401, got 500 |
| 45 | Dashboard pages require login | FAIL: /admin redirects to login (got 500) |
| 46 | Staff cannot open senior-admin pages | FAIL: staff sees bookings — expected 200, got 500 |
| 47 | Wrong passwords lock the account after 5 attempts | FAIL: correct password refused while locked — expected 401, got 500 |
| 48 | Forms without a valid CSRF token are refused | FAIL: review — expected 419, got 500 |
| 49 | Bookings cannot be opened without the private link or lookup | FAIL: no token — expected 404, got 500 |
| 50 | Script injection is rejected in names and escaped everywhere else | FAIL: name rejected — expected 422, got 500 |
| 51 | SQL-injection style input is treated as plain text | FAIL: search works — expected 200, got 500 |
| 52 | Array / malformed parameters do not crash the app | FAIL: validation error, not a crash — expected 422, got 500 |
| 53 | Booking lookup is rate limited | FAIL: got 429 after repeated attempts |
| 54 | Security headers are sent | PASS |
| 55 | CSV exports (bookings, items, payments) download with correct totals | FAIL: bookings csv — expected 200, got 500 |
| 56 | Daily / weekly / monthly summaries match verified payments | FAIL: verified revenue ₹0 shown |
| 57 | Admin changes prices; new bookings use them, old bookings keep theirs | FAIL: price saved — expected 55100, got 50100 |
| 58 | Food coupon catalog price drives booking totals | PASS |
| 59 | Disabled services disappear from booking and are refused | FAIL: refused — expected 422, got 500 |
| 60 | UPI QR only appears after payment details are verified | FAIL: fallback when UPI not verified and no gateway |
| 61 | Data persists: bookings, items, payments and audit are in the database | FAIL: bookings stored |
