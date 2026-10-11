"""Browser layout & interaction checks (Playwright + Chromium).

Usage:  python3 tests/ui_test.py http://127.0.0.1:8088/Booking admin 'AdminPassword' [screenshot_dir]

For each viewport (phone 360px, phone 390px, tablet 768px, desktop 1280px) it:
  * completes the booking wizard (service + food coupons) using the JavaScript UI,
  * checks no page scrolls sideways, no JavaScript errors, tap targets >= 44px,
  * submits a UPI reference and opens the status page.
It also completes a booking with JavaScript disabled, and opens the admin pages.
Exit code 0 when every check passes.
"""
import sys, re, datetime, random
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8088/Booking"
ADMIN_USER = sys.argv[2] if len(sys.argv) > 2 else "admin"
ADMIN_PASS = sys.argv[3] if len(sys.argv) > 3 else ""
SHOTS = sys.argv[4] if len(sys.argv) > 4 else None
VIEWPORTS = {"phone-360": (360, 740, True), "phone-390": (390, 844, True), "tablet-768": (768, 1024, True), "desktop-1280": (1280, 900, False)}
DATE = (datetime.date.today() + datetime.timedelta(days=6)).isoformat()
results = []


def check(name, cond, detail=""):
    results.append((name, bool(cond), detail))
    print(("  PASS " if cond else "  FAIL ") + name + ("" if cond else f"  [{detail}]"))


def no_overflow(page, label):
    w = page.evaluate("() => [document.documentElement.scrollWidth, window.innerWidth]")
    check(f"{label}: no sideways scrolling", w[0] <= w[1] + 1, f"scrollWidth {w[0]} > {w[1]}")


def small_targets(page):
    return page.evaluate("""() => [...document.querySelectorAll('main button, main .btn, main input[type=checkbox], main select, main input:not([type=hidden])')]
        .filter(e => e.offsetParent !== null)
        .map(e => { const r = e.getBoundingClientRect(); return [e.outerHTML.slice(0, 60), Math.round(r.width), Math.round(r.height)]; })
        .filter(([h, w, ht]) => Math.min(w, ht) < 24 || (ht < 44 && !h.includes('checkbox') && !h.includes('btn-small')))""")


def shot(page, name):
    if SHOTS:
        page.screenshot(path=f"{SHOTS}/{name}.png", full_page=True)


with sync_playwright() as p:
    browser = p.chromium.launch()
    for vp, (w, h, mobile) in VIEWPORTS.items():
        print(f"\n{vp}")
        ctx = browser.new_context(viewport={"width": w, "height": h}, is_mobile=mobile, has_touch=mobile)
        page = ctx.new_page()
        errors = []
        page.on("pageerror", lambda e: errors.append(str(e)))
        page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)

        page.goto(BASE + "/"); no_overflow(page, "home"); shot(page, f"{vp}-1-home")
        page.goto(BASE + "/book"); no_overflow(page, "step 1")
        check("step indicator visible", page.is_visible(".progress"))
        check("only the first step shown", page.is_visible("fieldset[data-step='1']") and not page.is_visible("fieldset[data-step='2']"))
        page.click("fieldset[data-step='1'] [data-next]")
        check("cannot continue without a service", page.is_visible("fieldset[data-step='1'] .js-error"))
        ids = page.eval_on_selector_all("input[name='services[]']", "els => els.map(e => e.value)")
        page.check(f"input[name='services[]'][value='{ids[0]}']")
        # Food-only must untick other services
        food_only = page.get_attribute("input[data-food-only='1']", "value")
        page.check(f"input[name='services[]'][value='{food_only}']")
        check("food-only clears other services", not page.is_checked(f"input[name='services[]'][value='{ids[0]}']"))
        page.check(f"input[name='services[]'][value='{ids[0]}']")
        check("other service clears food-only", not page.is_checked(f"input[name='services[]'][value='{food_only}']"))
        shot(page, f"{vp}-2-services")
        page.click("fieldset[data-step='1'] [data-next]")
        page.click("fieldset[data-step='2'] [data-next]")
        check("details step shows field errors", page.locator("fieldset[data-step='2'] .js-error").count() >= 3)
        page.fill("#name", "Sabitri Panda"); page.fill("#mobile", "9845012345")
        page.fill("#service_date", DATE); page.dispatch_event("#service_date", "change"); page.wait_for_timeout(500)
        sid = ids[0]
        opts = page.eval_on_selector_all(f"#slot-{sid} option:not([disabled])", "els => els.map(e => e.value).filter(Boolean)")
        page.select_option(f"#slot-{sid}", opts[0])
        for f in page.query_selector_all(f"[data-panel='{sid}'] [data-required]"):
            if f.evaluate("e => e.tagName") == "SELECT":
                f.select_option(index=1)
            else:
                f.fill("Panda family")
        no_overflow(page, "step 2"); shot(page, f"{vp}-3-details")
        page.click("fieldset[data-step='2'] [data-next]")
        check("food step reached", page.is_visible("fieldset[data-step='3']"))
        check("coupons hidden until 'Add food' ticked", not page.is_visible("[data-coupons]"))
        page.check("#add_food")
        cids = page.eval_on_selector_all("[data-coupon]", "els => els.map(e => e.dataset.coupon)")
        for _ in range(4):
            page.click(f"[data-inc='{cids[0]}']")
        for _ in range(2):
            page.click(f"[data-inc='{cids[1]}']")
        line = page.text_content(f"[data-line-total='{cids[0]}']")
        check("line total shown (rate × persons)", "× 4 =" in (line or ""), line)
        est = page.text_content("[data-estimate-total]")
        no_overflow(page, "step 3"); shot(page, f"{vp}-4-food")
        tiny = small_targets(page)
        check("tap targets large enough on the form", not tiny, str(tiny[:3]))
        page.click("fieldset[data-step='3'] button[type=submit]"); page.wait_for_load_state()
        total = page.text_content("tr.grand td")
        check("server total matches the on-screen estimate", (est or "").strip() == (total or "").strip(), f"{est} vs {total}")
        no_overflow(page, "review"); shot(page, f"{vp}-5-review")
        page.click("button:has-text('Submit booking')"); page.wait_for_load_state()
        check("submit without ticking confirm is blocked", "/book" in page.url or page.is_visible("[name=agree]"))
        page.check("input[name=agree]"); page.click("button:has-text('Submit booking')"); page.wait_for_load_state()
        m = re.search(r"/b/(SJT-\d{4}-\d{6})", page.url)
        check("booking created and number shown", bool(m) and page.is_visible(".booking-no"), page.url)
        no_overflow(page, "payment page"); shot(page, f"{vp}-6-pay")
        if page.is_visible("#reference"):
            page.fill("#reference", str(random.randint(10**11, 10**12 - 1)))
            page.click("button:has-text('Submit payment reference')"); page.wait_for_load_state()
            check("payment reference accepted, awaiting verification", page.is_visible("text=awaiting verification"))
        no_overflow(page, "status page"); shot(page, f"{vp}-7-status")
        page.goto(BASE + "/lookup"); no_overflow(page, "lookup")
        if m:
            page.goto(BASE + f"/b/{m.group(1)}/invoice"); no_overflow(page, "invoice")
        check("no JavaScript errors", not errors, "; ".join(errors[:3]))

        if ADMIN_PASS:
            page.goto(BASE + "/admin/login"); page.fill("input[name=username]", ADMIN_USER); page.fill("input[name=password]", ADMIN_PASS)
            page.click("button[type=submit]"); page.wait_for_load_state()
            for path in ["/admin", "/admin/bookings", "/admin/reports", "/admin/settings"]:
                page.goto(BASE + path)
                check(f"admin {path} loads", page.locator("h1").count() > 0)
            if m:
                page.goto(BASE + f"/admin/bookings/{m.group(1)}"); shot(page, f"{vp}-8-admin-booking")
        ctx.close()

    print("\nno-javascript")
    ctx = browser.new_context(java_script_enabled=False, viewport={"width": 390, "height": 844})
    page = ctx.new_page()
    page.goto(BASE + "/book")
    check("all steps visible without JavaScript", page.is_visible("fieldset[data-step='3']"))
    ids = page.eval_on_selector_all("input[name='services[]']", "els => els.map(e => e.value)")
    fo = page.get_attribute("input[data-food-only='1']", "value")
    page.check(f"input[name='services[]'][value='{fo}']")
    page.fill("#name", "Bijay Nayak"); page.fill("#mobile", "9845098450"); page.fill("#service_date", DATE)
    page.select_option(f"#slot-{fo}", index=1)
    cid = page.get_attribute("[data-coupon]", "data-coupon")
    page.check(f"[data-coupon-check='{cid}']"); page.fill(f"#food-{cid}", "3")
    page.click("button:has-text('Review booking')"); page.wait_for_load_state()
    check("no-JS review page reached", "Review your booking" in page.content())
    page.check("input[name=agree]"); page.click("button:has-text('Submit booking')"); page.wait_for_load_state()
    check("no-JS food-only booking created", bool(re.search(r"/b/SJT-\d{4}-\d{6}", page.url)), page.url)
    browser.close()

failed = [r for r in results if not r[1]]
print(f"\n{len(results) - len(failed)} / {len(results)} UI checks passed")
sys.exit(1 if failed else 0)
