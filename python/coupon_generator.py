"""
Food coupon generation for the temple's prasad/meal distribution.

Reuses the navy-blue ticket design established for the temple's printed
coupons: FOOD COUPON title, cost, and a serial number, with a perforated
tear-off edge. Renders each coupon as a PIL image, arranges them 4-per-row
on A4-ish pages, and saves the whole batch as a single multi-page PDF that
can be downloaded and printed directly from the browser.
"""
import os
from PIL import Image, ImageDraw, ImageFont

NAVY = (17, 44, 97, 255)
FONT_DIR = "/usr/share/fonts/truetype/dejavu/"

SCALE = 4
COUPON_W, COUPON_H = 297 * SCALE, 172 * SCALE

COUPONS_DIR = os.path.join(os.path.dirname(__file__), "coupons")
os.makedirs(COUPONS_DIR, exist_ok=True)


def _make_coupon_image(sl_no, coupon_name, cost):
    f_title = ImageFont.truetype(FONT_DIR + "DejaVuSerif-Bold.ttf", 26 * SCALE)
    f_amount = ImageFont.truetype(FONT_DIR + "DejaVuSerif-Bold.ttf", 20 * SCALE)
    f_slno = ImageFont.truetype(FONT_DIR + "DejaVuSans-Bold.ttf", 12 * SCALE)
    f_rupee = ImageFont.truetype(FONT_DIR + "DejaVuSans.ttf", 18 * SCALE)

    img = Image.new("RGBA", (COUPON_W, COUPON_H), (255, 255, 255, 0))
    d = ImageDraw.Draw(img)

    margin = 6 * SCALE
    perf_x = int(COUPON_W * 0.80)
    notch_r = 9 * SCALE
    border_w = 3 * SCALE

    d.rounded_rectangle([margin, margin, COUPON_W - margin, COUPON_H - margin],
                         radius=10 * SCALE, outline=NAVY, width=border_w)
    d.ellipse([perf_x - notch_r, margin - notch_r, perf_x + notch_r, margin + notch_r], fill=(255, 255, 255, 255))
    d.ellipse([perf_x - notch_r, COUPON_H - margin - notch_r, perf_x + notch_r, COUPON_H - margin + notch_r], fill=(255, 255, 255, 255))

    y = margin + notch_r + 2 * SCALE
    while y < COUPON_H - margin - notch_r:
        y2 = min(y + 6 * SCALE, COUPON_H - margin - notch_r)
        d.line([(perf_x, y), (perf_x, y2)], fill=NAVY, width=int(2.2 * SCALE))
        y += 11 * SCALE

    tx = margin + 12 * SCALE
    ty = margin + 8 * SCALE

    # Coupon name (title), wrapped to two lines if long
    words = coupon_name.upper().split()
    line1, line2 = coupon_name.upper(), ""
    if len(coupon_name) > 14 and len(words) > 1:
        mid = len(words) // 2
        line1, line2 = " ".join(words[:mid]), " ".join(words[mid:])

    d.text((tx, ty), line1, font=f_title, fill=NAVY)
    rule_y = ty + 30 * SCALE
    if line2:
        d.text((tx, ty + 28 * SCALE), line2, font=f_title, fill=NAVY)
        rule_y = ty + 58 * SCALE

    d.line([(tx, rule_y), (perf_x - 12 * SCALE, rule_y)], fill=NAVY, width=int(1.6 * SCALE))

    amt_y = rule_y + 8 * SCALE
    d.text((tx, amt_y), "\u20b9", font=f_rupee, fill=NAVY)
    d.text((tx + 20 * SCALE, amt_y - 2 * SCALE), f"{cost:.0f}/-", font=f_amount, fill=NAVY)

    slno_text = f"SL NO : {sl_no}"
    bbox = d.textbbox((0, 0), slno_text, font=f_slno)
    tw = bbox[2] - bbox[0]
    d.text((perf_x - 12 * SCALE - tw, amt_y + 4 * SCALE), slno_text, font=f_slno, fill=NAVY)

    # Composite onto a solid white background before downscaling — converting
    # a transparent RGBA image straight to RGB drops alpha without blending,
    # which renders as black instead of white.
    white_bg = Image.new("RGB", img.size, "white")
    white_bg.paste(img, (0, 0), mask=img.split()[3])
    return white_bg.resize((297, 172), Image.LANCZOS)


def generate_coupon_batch_pdf(batch_id, coupon_name, cost, start_sl_no, quantity):
    """Builds a printable multi-page PDF of `quantity` coupons, saved to coupons/batch_<id>.pdf."""
    cols = 4
    cell_w, cell_h = 330, 200  # px at 150dpi-ish page canvas
    page_w, page_h = 1275, 1650  # US-letter-ish at 150dpi
    margin_x, margin_y = 30, 30
    rows_per_page = (page_h - 2 * margin_y) // cell_h

    pages = []
    page_img = None
    draw_x = draw_y = 0
    count_on_page = 0

    for i in range(quantity):
        sl_no = start_sl_no + i
        coupon_img = _make_coupon_image(sl_no, coupon_name, cost)

        col = count_on_page % cols
        row = count_on_page // cols
        if col == 0 and row == 0:
            page_img = Image.new("RGB", (page_w, page_h), "white")
            pages.append(page_img)

        x = margin_x + col * cell_w
        y = margin_y + row * cell_h
        page_img.paste(coupon_img, (x + 10, y + 10))

        count_on_page += 1
        if count_on_page >= cols * rows_per_page:
            count_on_page = 0

    out_path = os.path.join(COUPONS_DIR, f"batch_{batch_id}.pdf")
    pages[0].save(out_path, save_all=True, append_images=pages[1:])
    return out_path
