# Configuration guide

Everything below is done in the admin dashboard (`/booking/admin`) by a **senior admin**,
except API secrets, which go only in the server's `.env` file.

The dashboard home shows a **"Before going live"** list until every item is done.
`php bin/console.php check` prints the same checks on the server.

---

## 1. Temple details — Settings › Temple details
Name, address, office phone, email, website, GSTIN (only if the temple is GST-registered),
and the terms shown on the review page and invoice. These appear on invoices, pages and
WhatsApp messages.

## 2. Authorised UPI QR code — Settings › UPI payment

**Never** enter a UPI ID or upload a QR that has not been confirmed by the temple treasurer.
The QR is only shown to devotees after a senior admin ticks *verified*.

### Option A — Dynamic QR (recommended)
1. Get the temple's **bank-issued UPI ID** (e.g. `jagannathtemple@sbi`) and the payee name
   registered with the bank, from the treasurer, in writing.
   A **merchant / current-account UPI ID** is strongly preferred: personal UPI IDs may show
   "limit exceeded" or risk warnings when an amount is pre-filled.
2. Enter it, choose *Dynamic*, save. The preview shows a test QR for ₹1.00.
3. Scan the preview with a phone, check the **payee name** shown by the UPI app, pay ₹1.
4. Ask the treasurer to confirm the ₹1 credit in the temple's bank statement.
5. Tick **"I verified that this UPI ID / QR pays into the temple's bank account"**, write who
   verified and when in the note, save.

Each booking then gets its own QR with the exact amount and the booking number as the payment
note, so most credits in the statement show the booking number directly.
If the bank provides a merchant VPA, tick *Include merchant transaction reference*.

### Option B — Static QR (the temple's existing printed QR)
1. Choose *Static*, save, then upload the official QR image (PNG/JPG). The image is re-encoded
   on upload (any embedded content is stripped).
2. Do the ₹1 test and tick *verified* as above. Devotees will type the amount themselves and are
   asked to write the booking number in the payment remarks.

Changing the UPI ID, switching mode or uploading a new image **automatically un-verifies** the
payment details until the test is repeated.

### Optional online gateway (Razorpay)
Only if the temple has an approved Razorpay account. In `.env`:
```
PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY_ID=rzp_live_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
```
In the Razorpay dashboard: enable **automatic capture**, and add a webhook to
`https://sjtbengaluru.org/Booking/webhooks/razorpay` for `payment.captured` and `order.paid`
with the same secret. A "Pay online" button then appears next to the UPI QR; bookings paid this
way are marked paid automatically after the server verifies Razorpay's signature.
*This module is implemented to Razorpay's published API but could not be tested against a live
Razorpay account; test it on staging with Razorpay test keys before enabling in production.*

## 3. WhatsApp — `.env` + Settings › WhatsApp

1. Temple obtains a **WhatsApp Business Platform (Cloud API)** account in Meta Business Manager
   (business verification, a dedicated phone number, a payment method). A Meta-approved
   provider (BSP) also works if it exposes the Cloud API.
2. Create the four templates in docs/WHATSAPP-TEMPLATES.md and wait for approval.
3. In Meta: create a **System User** with a **permanent access token** that has
   `whatsapp_business_messaging` permission. Note the **Phone number ID**.
4. On the server, edit `.env`:
   ```
   WA_ACCESS_TOKEN=<permanent system user token>
   WA_PHONE_NUMBER_ID=<phone number id>
   WA_APP_SECRET=<app secret, for the webhook>
   WA_WEBHOOK_VERIFY_TOKEN=<any random string>
   ```
   These are never shown in the dashboard and never sent to browsers.
5. Settings › WhatsApp: tick **Send WhatsApp notifications**, enter the **administrator WhatsApp
   number(s)** (comma separated), check the template names and language code, save.
6. Use **Send a test message** to your own phone.
7. Configure the webhook (see docs/WHATSAPP-TEMPLATES.md).

If WhatsApp is off or misconfigured, bookings continue normally; each unsent message appears
under **Notifications › Failed** with the reason. Temporary errors retry automatically
(cron, up to 5 attempts). Staff can *Retry* or *Mark handled* (e.g. after phoning the devotee).

## 4. Administrator notification number
Settings › WhatsApp › *Administrator WhatsApp number(s)*. Several numbers can receive alerts.
Each must be a WhatsApp-enabled mobile number.

## 5. Services and prices — Services & prices
- **Edit** a service to change name, description, price, rules and extra questions.
  Price `0` means "charges confirmed by the temple office": after reviewing the request, staff
  set the amount on the booking page (*Set additional charges*), and the devotee is notified to pay.
- **Rules**: needs approval · must choose a time slot · ask participants (with maximum) ·
  allow food coupons · can be combined with other services · maximum bookings per day ·
  minimum notice in days.
- **Extra details** to collect (e.g. gotra, vehicle number): label, type (short text, long text,
  choice list, number, date), required or optional.
- **Add a service** creates a new bookable card. Untick *Available for online booking* to hide
  a service (existing bookings are kept).
- The seed prices (₹501, ₹251, ₹1,101, ₹2,101, ₹5,001) are **placeholders**. When the committee
  has confirmed every price, press **I have reviewed all prices**.

Price changes apply only to new bookings; existing bookings and invoices keep their price.

## 6. Food / prasadam coupons — Food coupons
Edit name, price per person, maximum persons per booking, availability and order.
Add more categories if needed. Defaults: *Food Coupon (Standard)* ₹150, *Food Coupon (Special)* ₹250.

## 7. Availability, dates and time slots
- **Time slots** (per service): open the service › *Time slots*. Each slot has a label shown to
  devotees, start time, **capacity** (bookings per date; empty = unlimited), weekdays offered,
  and active/inactive.
- **Closed dates**: Availability › *Close dates for booking* — one day or a range, for all services
  or one service, with a reason shown to devotees (e.g. festival). Existing bookings on those
  dates are not changed; the page tells you how many exist so you can reschedule them.
- **Slot usage** for any date: Availability › *Slot usage*.
- **Booking window**: Settings › Booking rules — minimum notice (default 1 day) and how far ahead
  (default 90 days).

## 8. Administrator accounts — Admins
- *Senior admin*: everything. *Staff*: bookings, payments, availability, notifications, reports.
- Passwords: at least 10 characters with letters and numbers. 5 wrong attempts lock the account for
  15 minutes. Sessions end after 30 minutes idle.
- Forgotten password: another senior admin resets it, or on the server
  `php bin/console.php reset-password <username>`.

## 9. Other settings
- **Booking / invoice number prefixes** (Settings › Booking rules). Booking numbers restart each
  calendar year (SJT-2027-000001); invoice numbers each financial year (SJTB/2027-28/000001).
- **Additional charge per booking** (e.g. a convenience fee) — normally 0.
- **Rate limit** for submissions from one network: `.env` `BOOKING_RATE_LIMIT_PER_HOUR` (default 40),
  generous enough for the office to book on behalf of devotees.
