# Testing

## Automated tests

| Suite | Command | Latest result |
|---|---|---|
| End-to-end + unit (SQLite) | `php tests/run.php` | **60 / 60 passed** — TEST-RESULTS-SQLITE.md |
| End-to-end + unit (MySQL protocol) | `TEST_DB_DRIVER=mysql TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_USER=root TEST_DB_PASS=… php tests/run.php` | **60 / 60 passed** — TEST-RESULTS-MYSQL.md |
| Browser layout & interaction | `python3 tests/ui_test.py <url> <admin> <password> docs/screenshots` | **115 / 115 passed** — TEST-RESULTS-UI.md |

`tests/run.php` starts the real app with PHP's web server on a fresh database plus a mock WhatsApp
Cloud API, then drives every flow over HTTP exactly as a browser would (sessions, CSRF tokens,
redirects). The MySQL run was made against Dolt 1.x, a MySQL 8.0-compatible server, because no
MySQL/MariaDB package was installable in the development sandbox. It validates the MySQL schema,
SQL dialect and transaction handling; **run the suite once against the host's real MariaDB/MySQL
on staging** (any empty database user with CREATE DATABASE rights).

The browser suite (Playwright + Chromium) runs at 360px and 390px phones, 768px tablet and 1280px
desktop, and once with JavaScript disabled. Screenshots of every step are in `docs/screenshots/`.

### Coverage of the acceptance criteria (requirements §12)

| Requirement | Automated test(s) |
|---|---|
| Individual service booking | *Individual service booking (Special Puja)* |
| Multiple permitted services | *Multiple services in one booking*; *Food-only cannot be combined* |
| Service + ₹150 coupons | *Service booking with ₹150 food coupons (4 persons)* |
| Service + ₹250 coupons | *Service booking with ₹250 food coupons (2 persons)* |
| Combined coupon categories | *Combined coupon categories: 150×4 + 250×2 = ₹1,100* |
| Food-only booking | *Food-only booking*; *…requires a coupon category*; UI *no-JS food-only booking* |
| Multiple persons / quantities | coupon tests; *0 persons rejected*; *above maximum rejected*; UI line totals |
| Correct invoice totals | *Invoice: HTML and PDF…*; *Data persists* (every total = Σ lines, line = rate × qty); UI *server total matches estimate* |
| Unique booking numbers | *Concurrent submissions get unique, sequential booking numbers* (12 simultaneous) |
| Payment pending / verified | *Submitting a UPI reference does NOT mark paid*; *Admin verifies payment*; *Amount mismatch*; *Admin rejects a payment* |
| WhatsApp success / failure | *Enabled…status sent*; *Not configured…*; *Provider rejection…*; *Temporary failure…retry*; *Status-change messages*; *Delivery webhook* |
| Approval, cancellation, rescheduling | *Ceremony needing approval*; *Reschedule…*; *Cancel then refund*; *Cancelled bookings free their slot* |
| Duplicate submission, unavailable dates | *Duplicate submission…*; *Past dates…*; *Blocked dates…*; *Full time slot…*; *Price change between review and submit* |
| Mobile, tablet, desktop layouts | `tests/ui_test.py` (no sideways scroll, tap targets, no JS errors, at 4 widths + no-JS) |
| Unauthorised dashboard access | *Dashboard pages require login*; *Staff cannot open senior-admin pages*; *Wrong passwords lock…* |
| Invalid input & malicious requests | *Forms without CSRF…*; *Script injection…*; *SQL-injection style input…*; *Array / malformed parameters…*; *Tampered price fields…*; *Bookings cannot be opened without the private link*; *lookup is rate limited*; *Security headers* |
| Data persists, failures lose nothing | *Data persists…*; WhatsApp failure tests confirm bookings saved |

## Staging acceptance checklist (manual, on the real host)

Do these on https://sjtbengaluru.org/BookingStaging before approving production. Use real phones.

| # | Check | Result / date / initials |
|---|---|---|
| 1 | `php bin/console.php check` shows no WARN except items intentionally postponed | |
| 2 | `php tests/run.php` with `TEST_DB_DRIVER=mysql` against the host database server passes | |
| 3 | Booking on an Android phone (Chrome) and an iPhone (Safari): Special Puja + both coupon types | |
| 4 | Elderly volunteer completes a food-only booking unaided on their own phone | |
| 5 | Dynamic QR scanned with GPay, PhonePe and Paytm: payee name correct, amount and booking number pre-filled | |
| 6 | ₹1 real payment received in the temple bank account; UTR matches what the UPI app shows | |
| 7 | "Pay with UPI app" link opens a UPI app on Android | |
| 8 | WhatsApp: devotee acknowledgement, admin alert, payment-submitted alert and payment-verified message all arrive; links open | |
| 9 | WhatsApp delivered/read ticks appear in Notifications (webhook) | |
| 10 | Admin verifies a payment, approves a Shraddha booking, reschedules one, cancels one, records a refund | |
| 11 | PDF invoice opens and prints on A4 from phone and office computer | |
| 12 | CSV exports open in Excel with ₹ amounts and names correct | |
| 13 | Closed date and full slot are refused on the public form | |
| 14 | Staff account cannot open Settings / Services | |
| 15 | Backup cron produced a non-empty `.sql.gz`; restore into a copy works | |
| 16 | Prices, temple phone, terms confirmed by the committee | |

Sign-off: ______________________ (temple) Date: __________
