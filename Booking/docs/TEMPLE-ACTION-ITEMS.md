# What the temple must provide or approve before going live

The software is complete and tested. Production activation depends on the items below, which
only the temple (or its hosting provider / bank / Meta) can supply. Nothing here has been
assumed or filled in with real values.

## Required

| # | Item | Who | Why / where it is used |
|---|---|---|---|
| 1 | **cPanel access** to sjtbengaluru.org (login, or a developer sub-account), and confirmation the plan allows PHP 8.1+, MySQL databases and cron jobs | Temple / hosting provider | Installing staging and production (docs/DEPLOYMENT-CPANEL.md). The developer could not access the server; the site currently shows cPanel's "Coming Soon" page. |
| 2 | **Temple UPI ID (VPA) and registered payee name**, confirmed in writing by the treasurer, preferably a merchant / current-account UPI ID — or the official QR image | Treasurer / bank | Payment QR. Must pass the ₹1 test before devotees see it. |
| 3 | **WhatsApp Business Platform (Cloud API) account**: Meta Business verification, a dedicated phone number, a payment method for message charges, a permanent system-user token and the phone number ID | Temple (Meta) | Devotee and admin notifications. Can take several days — start early. Until ready, bookings work and messages are logged as failed. |
| 4 | **Approval of the 4 WhatsApp templates** in docs/WHATSAPP-TEMPLATES.md | Temple submits in Meta WhatsApp Manager | Meta must approve before messages can be sent. |
| 5 | **Administrator WhatsApp number(s)** for new-booking alerts | Temple office | Settings › WhatsApp |
| 6 | **Final prices** for every service, and confirmation of the ₹150 / ₹250 coupon categories | Temple committee | The installed service prices are placeholders (₹501, ₹251, ₹1,101, ₹2,101, ₹5,001). |
| 7 | **Service rules**: which services need approval, time slots and capacities, weekdays, festival closures, minimum notice | Temple office / priests | Defaults are reasonable guesses and fully editable. |
| 8 | **Temple contact details** for invoices and messages: office phone, email, full address; GSTIN only if registered | Temple office | Settings › Temple details |
| 9 | **Names and roles of administrators** (senior admin vs staff) | Temple | Personal accounts for each person; no shared passwords. |
| 10 | **Approval of staging** after the acceptance checklist (docs/TESTING.md) | Temple | Production is switched on only after sign-off. |

## Optional

| Item | Notes |
|---|---|
| Razorpay (or another approved gateway) account and API/webhook keys | Enables automatic online payment confirmation. Implemented for Razorpay; must be tested on staging with test keys first. |
| Privacy notice text for devotees | Recommended under India's DPDP Act 2023 — what data is collected (name, mobile, optional email, booking details), why, and whom to contact. Can be added to Settings › Terms. |
| Temple logo artwork | A simple original temple-spire icon is used; replace `public/assets/img/logo.svg` with the official logo. |
| Off-site backup location | Google Drive / external disk for weekly backup copies (docs/BACKUP-RECOVERY.md). |

## Deliberate limits to be aware of
- **UPI payments are confirmed by people, not automatically.** UPI QR payments do not notify the
  website; staff must check the bank statement. This is intentional, so that no booking is marked
  paid on a devotee's word. Automatic confirmation needs a payment gateway (item above).
- **Food coupons apply to the whole booking**, not separately to each puja in a multi-service
  booking. If the temple needs per-service food counts, tell the developer.
- **English only.** Odia and Kannada text can be added later.
- **No online cancellation by devotees.** Cancellations go through the temple office, which also
  handles refunds.
