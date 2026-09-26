"""PDF receipt generation for donations, triggered on admin button press."""
import os
from datetime import datetime
from reportlab.lib.pagesizes import A5
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas
from reportlab.lib.utils import simpleSplit, ImageReader

NAVY = HexColor("#7A1626")   # maroon, matches the temple site's palette
GOLD = HexColor("#C98A2B")
DARK = HexColor("#222222")
GREY = HexColor("#5A6270")

RECEIPTS_DIR = os.path.join(os.path.dirname(__file__), "receipts")
os.makedirs(RECEIPTS_DIR, exist_ok=True)
LOGO_PATH = os.path.join(os.path.dirname(__file__), "static", "logo.png")


def next_receipt_number(conn):
    year = datetime.now().year
    row = conn.execute(
        "SELECT receipt_number FROM donations WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1",
        (f"RCPT-{year}-%",),
    ).fetchone()
    if row and row["receipt_number"]:
        last_seq = int(row["receipt_number"].split("-")[-1])
        seq = last_seq + 1
    else:
        seq = 1
    return f"RCPT-{year}-{seq:04d}"


def generate_receipt_pdf(donation_row, donor_row, receipt_number):
    """donation_row and donor_row are sqlite3.Row objects."""
    filename = f"{receipt_number}.pdf"
    filepath = os.path.join(RECEIPTS_DIR, filename)

    w, h = A5
    c = canvas.Canvas(filepath, pagesize=A5)

    # Border
    c.setStrokeColor(GOLD)
    c.setLineWidth(2)
    c.rect(8 * mm, 8 * mm, w - 16 * mm, h - 16 * mm)

    # Header
    if os.path.exists(LOGO_PATH):
        logo_size = 16 * mm
        c.drawImage(ImageReader(LOGO_PATH), w / 2 - logo_size / 2, h - 20 * mm,
                    width=logo_size, height=logo_size, mask='auto')
        name_y = h - 24 * mm
    else:
        name_y = h - 22 * mm

    c.setFillColor(NAVY)
    c.setFont("Helvetica-Bold", 16)
    c.drawCentredString(w / 2, name_y, "SHREE JAGANNATH TEMPLE")
    c.setFont("Helvetica", 9)
    c.setFillColor(GREY)
    c.drawCentredString(w / 2, name_y - 5 * mm, "Sarjapura, Bengaluru")
    c.setFillColor(GOLD)
    c.setFont("Helvetica-Bold", 12)
    c.drawCentredString(w / 2, name_y - 12 * mm, "DONATION RECEIPT")

    c.setStrokeColor(GOLD)
    c.setLineWidth(0.7)
    c.line(14 * mm, name_y - 15 * mm, w - 14 * mm, name_y - 15 * mm)

    # Body
    y = name_y - 23 * mm
    line_gap = 7 * mm
    c.setFillColor(DARK)

    def field(label, value):
        nonlocal y
        c.setFont("Helvetica-Bold", 9.5)
        c.drawString(14 * mm, y, f"{label}:")
        c.setFont("Helvetica", 9.5)
        c.drawString(48 * mm, y, str(value))
        y -= line_gap

    field("Receipt No.", receipt_number)
    field("Date", donation_row["donation_date"])
    field("Donor Name", donor_row["name"])
    if donor_row["phone"]:
        field("Phone", donor_row["phone"])
    if donor_row["pan_number"]:
        field("PAN", donor_row["pan_number"])
    field("Donation Type", donation_row["donation_type"])
    if donation_row["amount"]:
        field("Amount", f"Rs. {donation_row['amount']:,.2f}")
    field("Purpose", donation_row["purpose"] or "General")
    field("Payment Mode", donation_row["payment_mode"])

    y -= 3 * mm
    c.setStrokeColor(HexColor("#DDDDDD"))
    c.line(14 * mm, y, w - 14 * mm, y)
    y -= 8 * mm

    c.setFont("Helvetica-Oblique", 8.5)
    c.setFillColor(GREY)
    note = "Thank you for your generous contribution towards Mahaprasad and temple seva. This receipt is issued for your records."
    for wrapped in simpleSplit(note, "Helvetica-Oblique", 8.5, w - 32 * mm):
        c.drawString(14 * mm, y, wrapped)
        y -= 5 * mm

    y -= 10 * mm
    c.setFont("Helvetica", 9)
    c.setFillColor(DARK)
    c.drawString(14 * mm, y, "Authorized Signatory: ______________________")

    c.showPage()
    c.save()
    return filepath
