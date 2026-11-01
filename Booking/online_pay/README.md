# Online payment (gateway adapters)

Devotee **Pay now** (card / UPI via provider) lives here. Manual temple UPI QR + UTR remains in `app/Service/UpiPayment.php`.

## Layout

| Path | Role |
|------|------|
| `Contract/GatewayAdapter.php` | Interface each provider implements |
| `Adapters/RazorpayAdapter.php` | Razorpay (current default) |
| `Adapters/NullAdapter.php` | `PAYMENT_GATEWAY=none` |
| `GatewayFactory.php` | Maps `.env` → adapter |
| `OnlinePaymentService.php` | DB order rows + adapter (used by controllers) |

## Configure

```env
PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=   # optional until webhook URL is set
BOOKING_UPI_MANUAL=false
```

## Add another gateway (e.g. PayU, Cashfree)

1. Create `Adapters/YourProviderAdapter.php` implementing `GatewayAdapter`.
2. Register it in `GatewayFactory::create()` (match a new `PAYMENT_GATEWAY` value).
3. Add `.env` keys for that provider only in `.env.example` + `docs/CONFIGURATION.md`.
4. If checkout needs browser JS, return `checkoutScriptUrl()` and extend `assets/js/app.js` (or add `online_pay/assets/yourprovider.js`) to open that checkout using the JSON from `POST /b/{no}/gateway/order`.
5. Webhook: implement `verifyWebhookSignature` + `parseWebhookCaptures`, then expose URL  
   `POST /webhooks/payment` (provider-specific paths like `/webhooks/razorpay` remain as aliases).

HTTP routes and admin booking screens stay unchanged; they use `gateway_order_id` / `gateway_payment_id` columns generically.
