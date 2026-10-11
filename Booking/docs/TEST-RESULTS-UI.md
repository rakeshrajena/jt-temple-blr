# Browser (UI) test results

Run: 2026-10-10 09:10 IST · Chromium (Playwright) · viewports 360px, 390px, 768px, 1280px and JavaScript disabled

Command: `python3 tests/ui_test.py <url> <admin user> <password> docs/screenshots`

```
phone-360
  PASS home: no sideways scrolling
  PASS step 1: no sideways scrolling
  PASS step indicator visible
  PASS only the first step shown
  PASS cannot continue without a service
  PASS food-only clears other services
  PASS other service clears food-only
  PASS details step shows field errors
  PASS step 2: no sideways scrolling
  PASS food step reached
  PASS coupons hidden until 'Add food' ticked
  PASS line total shown (rate × persons)
  PASS step 3: no sideways scrolling
  PASS tap targets large enough on the form
  PASS server total matches the on-screen estimate
  PASS review: no sideways scrolling
  PASS submit without ticking confirm is blocked
  PASS booking created and number shown
  PASS payment page: no sideways scrolling
  PASS payment reference accepted, awaiting verification
  PASS status page: no sideways scrolling
  PASS lookup: no sideways scrolling
  PASS invoice: no sideways scrolling
  PASS no JavaScript errors
  PASS admin /admin loads
  PASS admin /admin/bookings loads
  PASS admin /admin/reports loads
  PASS admin /admin/settings loads

phone-390
  PASS home: no sideways scrolling
  PASS step 1: no sideways scrolling
  PASS step indicator visible
  PASS only the first step shown
  PASS cannot continue without a service
  PASS food-only clears other services
  PASS other service clears food-only
  PASS details step shows field errors
  PASS step 2: no sideways scrolling
  PASS food step reached
  PASS coupons hidden until 'Add food' ticked
  PASS line total shown (rate × persons)
  PASS step 3: no sideways scrolling
  PASS tap targets large enough on the form
  PASS server total matches the on-screen estimate
  PASS review: no sideways scrolling
  PASS submit without ticking confirm is blocked
  PASS booking created and number shown
  PASS payment page: no sideways scrolling
  PASS payment reference accepted, awaiting verification
  PASS status page: no sideways scrolling
  PASS lookup: no sideways scrolling
  PASS invoice: no sideways scrolling
  PASS no JavaScript errors
  PASS admin /admin loads
  PASS admin /admin/bookings loads
  PASS admin /admin/reports loads
  PASS admin /admin/settings loads

tablet-768
  PASS home: no sideways scrolling
  PASS step 1: no sideways scrolling
  PASS step indicator visible
  PASS only the first step shown
  PASS cannot continue without a service
  PASS food-only clears other services
  PASS other service clears food-only
  PASS details step shows field errors
  PASS step 2: no sideways scrolling
  PASS food step reached
  PASS coupons hidden until 'Add food' ticked
  PASS line total shown (rate × persons)
  PASS step 3: no sideways scrolling
  PASS tap targets large enough on the form
  PASS server total matches the on-screen estimate
  PASS review: no sideways scrolling
  PASS submit without ticking confirm is blocked
  PASS booking created and number shown
  PASS payment page: no sideways scrolling
  PASS payment reference accepted, awaiting verification
  PASS status page: no sideways scrolling
  PASS lookup: no sideways scrolling
  PASS invoice: no sideways scrolling
  PASS no JavaScript errors
  PASS admin /admin loads
  PASS admin /admin/bookings loads
  PASS admin /admin/reports loads
  PASS admin /admin/settings loads

desktop-1280
  PASS home: no sideways scrolling
  PASS step 1: no sideways scrolling
  PASS step indicator visible
  PASS only the first step shown
  PASS cannot continue without a service
  PASS food-only clears other services
  PASS other service clears food-only
  PASS details step shows field errors
  PASS step 2: no sideways scrolling
  PASS food step reached
  PASS coupons hidden until 'Add food' ticked
  PASS line total shown (rate × persons)
  PASS step 3: no sideways scrolling
  PASS tap targets large enough on the form
  PASS server total matches the on-screen estimate
  PASS review: no sideways scrolling
  PASS submit without ticking confirm is blocked
  PASS booking created and number shown
  PASS payment page: no sideways scrolling
  PASS payment reference accepted, awaiting verification
  PASS status page: no sideways scrolling
  PASS lookup: no sideways scrolling
  PASS invoice: no sideways scrolling
  PASS no JavaScript errors
  PASS admin /admin loads
  PASS admin /admin/bookings loads
  PASS admin /admin/reports loads
  PASS admin /admin/settings loads

no-javascript
  PASS all steps visible without JavaScript
  PASS no-JS review page reached
  PASS no-JS food-only booking created

115 / 115 UI checks passed
```

Screenshots of every step at each viewport are in `docs/screenshots/`.
