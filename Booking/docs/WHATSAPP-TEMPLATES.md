# WhatsApp message templates

WhatsApp only allows a business to start a conversation with an **approved template**.
Create these four templates in **Meta Business Suite › WhatsApp Manager › Message templates**.

- Category: **Utility** · Language: **English** (code `en`; if you choose *English (US)* use `en_US`
  and enter that code in Settings › WhatsApp).
- The names below are the defaults in Settings › WhatsApp; if Meta requires a different name, put
  the approved name in the matching setting.
- The variables `{{1}} … {{n}}` must be in exactly this order — the app fills them in this order.
- Meta asks for sample values; suggestions are given. Approval usually takes minutes to a day.
- Meta does not allow a template to start or end with a variable, so each one ends with a short
  fixed sentence. No buttons, header or footer are required. (Links are sent as text so they work everywhere.)

---

## 1. `sjt_booking_received` — to the devotee after booking

```
🙏 Jai Jagannath, {{1}}.

Your booking request {{2}} at Shree Jagannatha Temple Bengaluru has been received.

Services: {{3}}
Date and time: {{4}}
Food coupons: {{5}}
Total amount: {{6}}
Status: {{7}}

View your booking, invoice and payment QR: {{8}}

Temple office: {{9}}
Please quote your booking number in all communication.
```
Samples: `Ananya Sahu` · `SJT-2026-000123` · `Special Puja, Vehicle Puja` · `Fri, 16 Oct 2026, Morning (7:00 AM – 9:00 AM)` · `Food Coupon (Standard) × 4, Food Coupon (Special) × 2` · `₹1,852` · `Booking request received — payment pending` · `https://sjtbengaluru.org/booking/b/SJT-2026-000123?t=abc` · `Shree Jagannatha Temple Bengaluru, +91 80 1234 5678`

## 2. `sjt_booking_update` — to the devotee on every status change

Used for: payment verified, payment not verified, approved, not approved, cancelled,
rescheduled, charges changed, refund recorded.

```
🙏 Jai Jagannath, {{1}}.

Update on your booking {{2}}:
{{3}}

Booking status: {{4}}
Payment status: {{5}}

Booking details and invoice: {{6}}

Temple office: {{7}}
Thank you.
```
Samples: `Ananya Sahu` · `SJT-2026-000123` · `Your payment of ₹1,852 (ref 427812345678) has been verified. Your booking is confirmed.` · `Confirmed` · `Paid (verified)` · `https://sjtbengaluru.org/booking/b/SJT-2026-000123?t=abc` · `Shree Jagannatha Temple Bengaluru, +91 80 1234 5678`

## 3. `sjt_admin_new_booking` — to temple administrators

```
New temple booking {{1}}

Devotee: {{2}} ({{3}})
Services: {{4}}
Date and time: {{5}}
Food coupons: {{6}}
Total: {{7}}
Status: {{8}}

Open in dashboard: {{9}}
Sign-in required.
```
Samples: `SJT-2026-000123` · `Ananya Sahu` · `+91 98450 12345` · `Special Puja` · `Fri, 16 Oct 2026, Morning (7:00 AM – 9:00 AM)` · `Food Coupon (Standard) × 4` · `₹1,101` · `Booking request received — payment pending` · `https://sjtbengaluru.org/booking/admin/bookings/SJT-2026-000123`

(The dashboard link asks for the admin password; it does not expose the booking publicly.)

## 4. `sjt_admin_payment_submitted` — to administrators when a devotee submits a UTR

```
Payment reference submitted for booking {{1}}.

Devotee: {{2}}
Amount due: {{3}}
UPI reference (UTR): {{4}}

Please check the temple bank statement and verify: {{5}}
Do not mark as paid until the credit is visible.
```
Samples: `SJT-2026-000123` · `Ananya Sahu` · `₹1,101` · `427812345678` · `https://sjtbengaluru.org/booking/admin/bookings/SJT-2026-000123`

---

## Webhook (delivery reports — optional but recommended)

In the Meta App Dashboard › WhatsApp › Configuration:
- **Callback URL:** `https://sjtbengaluru.org/booking/webhooks/whatsapp`
- **Verify token:** the value of `WA_WEBHOOK_VERIFY_TOKEN` in `.env`
- Subscribe to the **messages** field.

The app checks Meta's `X-Hub-Signature-256` with `WA_APP_SECRET` and updates each message to
*delivered* / *read* / *failed* in Dashboard › Notifications. Without the webhook, messages show
*sent* (accepted by WhatsApp) and permanent delivery failures are not reported back.

## Important WhatsApp rules

- Devotees receive template messages without having messaged the temple first, but Meta may
  block messages to users who report spam — keep messages to booking matters only.
- Per-message charges apply for utility templates (billed by Meta to the WhatsApp Business account).
- The temple's WhatsApp Business number used for the API **cannot** simultaneously be used in the
  normal WhatsApp / WhatsApp Business phone app unless the provider supports "coexistence".
  Use a dedicated number if unsure.
