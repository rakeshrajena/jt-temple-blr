import os
import secrets
from datetime import date, datetime
from functools import wraps

from flask import (
    Flask, render_template, request, redirect, url_for, session, flash,
    send_from_directory, jsonify
)
from werkzeug.security import check_password_hash, generate_password_hash
from werkzeug.utils import secure_filename

from database import get_db, init_db
from receipt_generator import generate_receipt_pdf, next_receipt_number, RECEIPTS_DIR
from reconciliation import parse_statement_file, reconcile_transactions
from coupon_generator import generate_coupon_batch_pdf, COUPONS_DIR
import reports as reports_mod

MAX_ADMIN_USERS = 10
UPLOAD_DIR = os.path.join(os.path.dirname(__file__), "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

app = Flask(__name__)
app.secret_key = "temple-admin-dev-secret-change-in-production"

init_db()


# ---------------------------------------------------------------
# Auth helpers
# ---------------------------------------------------------------
def login_required(f):
    @wraps(f)
    def wrapper(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login", next=request.path))
        return f(*args, **kwargs)
    return wrapper


def admin_required(f):
    @wraps(f)
    def wrapper(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login"))
        if session.get("role") != "Admin":
            flash("This section is restricted to Admin users.", "error")
            return redirect(url_for("dashboard"))
        return f(*args, **kwargs)
    return wrapper


@app.context_processor
def inject_user():
    return {"current_user": {
        "id": session.get("user_id"),
        "full_name": session.get("full_name"),
        "role": session.get("role"),
        "username": session.get("username"),
    }}


# ---------------------------------------------------------------
# Auth routes
# ---------------------------------------------------------------
@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        username = request.form["username"].strip()
        password = request.form["password"]
        conn = get_db()
        user = conn.execute(
            "SELECT * FROM users WHERE username=? AND is_active=1", (username,)
        ).fetchone()
        if user and check_password_hash(user["password_hash"], password):
            session["user_id"] = user["id"]
            session["username"] = user["username"]
            session["full_name"] = user["full_name"]
            session["role"] = user["role"]
            return redirect(request.args.get("next") or url_for("dashboard"))
        flash("Invalid username or password.", "error")
    return render_template("login.html")


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


# ---------------------------------------------------------------
# Dashboard
# ---------------------------------------------------------------
@app.route("/")
@login_required
def dashboard():
    conn = get_db()
    summary = reports_mod.dashboard_summary(conn)
    recent_donations = conn.execute(
        """SELECT d.*, don.name as donor_name FROM donations d
           JOIN donors don ON d.donor_id = don.id
           ORDER BY d.donation_date DESC LIMIT 5"""
    ).fetchall()
    recent_expenses = conn.execute(
        "SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 5"
    ).fetchall()
    low_stock_items = conn.execute(
        "SELECT * FROM food_items WHERE current_stock <= minimum_threshold"
    ).fetchall()
    return render_template(
        "dashboard.html", summary=summary, recent_donations=recent_donations,
        recent_expenses=recent_expenses, low_stock_items=low_stock_items, active="dashboard",
    )


# ---------------------------------------------------------------
# Inventory (hardware / general items)
# ---------------------------------------------------------------
@app.route("/inventory", methods=["GET", "POST"])
@login_required
def inventory():
    conn = get_db()
    if request.method == "POST":
        conn.execute(
            """INSERT INTO inventory_items (category, name, description, quantity, unit,
               item_condition, location, source, added_date, added_by, notes)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)""",
            (request.form["category"], request.form["name"], request.form.get("description"),
             int(request.form["quantity"]), request.form.get("unit", "pcs"),
             request.form.get("item_condition", "Good"), request.form.get("location"),
             request.form.get("source", "Purchased"), date.today().isoformat(),
             session["user_id"], request.form.get("notes")),
        )
        conn.commit()
        flash("Inventory item added.", "success")
        return redirect(url_for("inventory"))

    items = conn.execute("SELECT * FROM inventory_items ORDER BY category, name").fetchall()
    categories = ["Hardware", "Electronics", "Furniture", "Puja Items", "Kitchen Equipment", "Decoration", "Other"]
    return render_template("inventory.html", items=items, categories=categories, active="inventory")


# ---------------------------------------------------------------
# Food items — stock + usage log
# ---------------------------------------------------------------
@app.route("/food", methods=["GET", "POST"])
@login_required
def food():
    conn = get_db()
    if request.method == "POST":
        action = request.form["action"]
        if action == "new_item":
            conn.execute(
                "INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)",
                (request.form["name"], request.form.get("unit", "kg"),
                 float(request.form.get("current_stock", 0)), float(request.form.get("minimum_threshold", 0))),
            )
            conn.commit()
            flash("Food item added to stock list.", "success")
        elif action in ("add_stock", "use_stock"):
            food_id = int(request.form["food_item_id"])
            qty = float(request.form["quantity"])
            purpose = request.form.get("purpose")
            txn_type = "Added" if action == "add_stock" else "Used"
            delta = qty if txn_type == "Added" else -qty
            conn.execute(
                "UPDATE food_items SET current_stock = current_stock + ?, last_updated=? WHERE id=?",
                (delta, datetime.now().isoformat(), food_id),
            )
            conn.execute(
                "INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)",
                (food_id, txn_type, qty, purpose, date.today().isoformat(), session["user_id"]),
            )
            conn.commit()
            flash(f"Stock {'added' if txn_type=='Added' else 'usage logged'}.", "success")
        return redirect(url_for("food"))

    items = conn.execute("SELECT * FROM food_items ORDER BY name").fetchall()
    logs = conn.execute(
        """SELECT l.*, f.name as food_name, f.unit FROM food_usage_log l
           JOIN food_items f ON l.food_item_id = f.id
           ORDER BY l.txn_date DESC, l.id DESC LIMIT 30"""
    ).fetchall()
    return render_template("food.html", items=items, logs=logs, active="food")


# ---------------------------------------------------------------
# Food Coupon Generator — printable prasad/meal coupons, cost-tracked
# ---------------------------------------------------------------
@app.route("/food/coupons", methods=["GET", "POST"])
@login_required
def food_coupons():
    conn = get_db()
    if request.method == "POST":
        coupon_name = request.form["coupon_name"].strip()
        cost = float(request.form["cost"])
        quantity = int(request.form["quantity"])

        last = conn.execute("SELECT MAX(end_sl_no) as m FROM food_coupon_batches").fetchone()
        start_sl_no = (last["m"] + 1) if last and last["m"] else 1
        end_sl_no = start_sl_no + quantity - 1
        total_value = cost * quantity

        cur = conn.execute(
            """INSERT INTO food_coupon_batches (coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, created_date, created_by)
               VALUES (?,?,?,?,?,?,?,?)""",
            (coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, date.today().isoformat(), session["user_id"]),
        )
        batch_id = cur.lastrowid
        conn.commit()

        generate_coupon_batch_pdf(batch_id, coupon_name, cost, start_sl_no, quantity)
        flash(f"Generated {quantity} coupons for \u201c{coupon_name}\u201d — Sl No {start_sl_no} to {end_sl_no} (total value \u20b9{total_value:,.0f}).", "success")
        return redirect(url_for("food_coupons"))

    batches = conn.execute(
        """SELECT b.*, u.full_name as created_by_name FROM food_coupon_batches b
           LEFT JOIN users u ON b.created_by = u.id ORDER BY b.id DESC"""
    ).fetchall()
    total_coupons_value = conn.execute("SELECT COALESCE(SUM(total_value),0) FROM food_coupon_batches").fetchone()[0]
    return render_template("food_coupons.html", batches=batches, total_coupons_value=total_coupons_value, active="food")


@app.route("/food/coupons/<int:batch_id>/print")
@login_required
def print_coupon_batch(batch_id):
    filepath = os.path.join(COUPONS_DIR, f"batch_{batch_id}.pdf")
    if not os.path.exists(filepath):
        batch = get_db().execute("SELECT * FROM food_coupon_batches WHERE id=?", (batch_id,)).fetchone()
        if not batch:
            flash("Coupon batch not found.", "error")
            return redirect(url_for("food_coupons"))
        generate_coupon_batch_pdf(batch_id, batch["coupon_name"], batch["cost"], batch["start_sl_no"], batch["quantity"])
    return send_from_directory(COUPONS_DIR, f"batch_{batch_id}.pdf")


# ---------------------------------------------------------------
# Vastra (deity cloths)
# ---------------------------------------------------------------
@app.route("/vastra", methods=["GET", "POST"])
@login_required
def vastra():
    conn = get_db()
    if request.method == "POST":
        conn.execute(
            """INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status, notes)
               VALUES (?,?,?,?,?,?,?,?)""",
            (request.form["deity_name"], request.form["item_name"], request.form.get("color"),
             int(request.form.get("quantity", 1)), request.form.get("source", "Purchased"),
             date.today().isoformat(), request.form.get("status", "In Store"), request.form.get("notes")),
        )
        conn.commit()
        flash("Vastra item added.", "success")
        return redirect(url_for("vastra"))

    items = conn.execute("SELECT * FROM vastra_items ORDER BY deity_name, item_name").fetchall()
    return render_template("vastra.html", items=items, active="vastra")


# ---------------------------------------------------------------
# Donors + Donations
# ---------------------------------------------------------------
@app.route("/donations", methods=["GET", "POST"])
@login_required
def donations():
    conn = get_db()
    if request.method == "POST":
        # find-or-create donor by phone (simple de-dup key)
        phone = request.form.get("donor_phone", "").strip()
        name = request.form["donor_name"].strip()
        donor = None
        if phone:
            donor = conn.execute("SELECT * FROM donors WHERE phone=?", (phone,)).fetchone()
        if not donor:
            cur = conn.execute(
                "INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)",
                (name, phone, request.form.get("donor_email"), request.form.get("donor_address"),
                 request.form.get("pan_number")),
            )
            donor_id = cur.lastrowid
        else:
            donor_id = donor["id"]

        donation_type = request.form["donation_type"]
        amount = request.form.get("amount") or None
        amount = float(amount) if amount else None

        cur = conn.execute(
            """INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date,
               payment_mode, created_by) VALUES (?,?,?,?,?,?,?)""",
            (donor_id, donation_type, amount, request.form.get("purpose", "General"),
             request.form.get("donation_date", date.today().isoformat()),
             request.form["payment_mode"], session["user_id"]),
        )
        donation_id = cur.lastrowid

        # In-kind donations auto-add to the relevant inventory
        if donation_type == "Food" and request.form.get("food_item_id"):
            food_id = int(request.form["food_item_id"])
            qty = float(request.form.get("in_kind_quantity", 0))
            conn.execute("UPDATE food_items SET current_stock = current_stock + ? WHERE id=?", (qty, food_id))
            conn.execute(
                "INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)",
                (food_id, "Added", qty, f"Donation from {name}", date.today().isoformat(), session["user_id"]),
            )
            conn.execute("UPDATE donations SET linked_food_id=? WHERE id=?", (food_id, donation_id))
        elif donation_type == "Vastra" and request.form.get("vastra_deity"):
            cur2 = conn.execute(
                """INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, donation_id, date_added, status)
                   VALUES (?,?,?,?,?,?,?,?)""",
                (request.form["vastra_deity"], request.form.get("vastra_item_name", "Vastra"),
                 request.form.get("vastra_color"), int(request.form.get("in_kind_quantity", 1)),
                 "Donated", donation_id, date.today().isoformat(), "In Store"),
            )
            conn.execute("UPDATE donations SET linked_vastra_id=? WHERE id=?", (cur2.lastrowid, donation_id))
        elif donation_type == "Inventory" and request.form.get("inventory_name"):
            cur3 = conn.execute(
                """INSERT INTO inventory_items (category, name, quantity, unit, source, donation_id, added_date, added_by, notes)
                   VALUES (?,?,?,?,?,?,?,?,?)""",
                (request.form.get("inventory_category", "Other"), request.form["inventory_name"],
                 int(request.form.get("in_kind_quantity", 1)), request.form.get("inventory_unit", "pcs"),
                 "Donated", donation_id, date.today().isoformat(), session["user_id"],
                 f"Donated by {name}"),
            )
            conn.execute("UPDATE donations SET linked_inventory_id=? WHERE id=?", (cur3.lastrowid, donation_id))

        conn.commit()
        flash("Donation recorded.", "success")
        return redirect(url_for("donations"))

    donation_list = conn.execute(
        """SELECT d.*, don.name as donor_name, don.phone as donor_phone FROM donations d
           JOIN donors don ON d.donor_id = don.id ORDER BY d.donation_date DESC"""
    ).fetchall()
    donors = conn.execute("SELECT * FROM donors ORDER BY name").fetchall()
    food_items = conn.execute("SELECT * FROM food_items ORDER BY name").fetchall()
    return render_template("donations.html", donations=donation_list, donors=donors, food_items=food_items, active="donations")


@app.route("/donations/<int:donation_id>/generate_receipt", methods=["POST"])
@login_required
def generate_receipt(donation_id):
    conn = get_db()
    donation = conn.execute("SELECT * FROM donations WHERE id=?", (donation_id,)).fetchone()
    if not donation:
        flash("Donation not found.", "error")
        return redirect(url_for("donations"))
    donor = conn.execute("SELECT * FROM donors WHERE id=?", (donation["donor_id"],)).fetchone()

    receipt_number = donation["receipt_number"] or next_receipt_number(conn)
    generate_receipt_pdf(donation, donor, receipt_number)

    conn.execute(
        "UPDATE donations SET receipt_number=?, receipt_generated=1 WHERE id=?",
        (receipt_number, donation_id),
    )
    conn.execute(
        "INSERT OR IGNORE INTO receipts (donation_id, receipt_number, generated_by) VALUES (?,?,?)",
        (donation_id, receipt_number, session["user_id"]),
    )
    conn.commit()
    flash(f"Receipt {receipt_number} generated.", "success")
    return redirect(url_for("donations"))


@app.route("/receipts/<path:filename>")
@login_required
def serve_receipt(filename):
    return send_from_directory(RECEIPTS_DIR, filename)


# ---------------------------------------------------------------
# Expenses
# ---------------------------------------------------------------
@app.route("/expenses", methods=["GET", "POST"])
@login_required
def expenses():
    conn = get_db()
    if request.method == "POST":
        conn.execute(
            """INSERT INTO expenses (category, description, amount, paid_to, expense_date,
               payment_mode, receipt_ref, added_by) VALUES (?,?,?,?,?,?,?,?)""",
            (request.form["category"], request.form.get("description"),
             float(request.form["amount"]), request.form.get("paid_to"),
             request.form.get("expense_date", date.today().isoformat()),
             request.form["payment_mode"], request.form.get("receipt_ref"), session["user_id"]),
        )
        conn.commit()
        flash("Expense recorded.", "success")
        return redirect(url_for("expenses"))

    expense_list = conn.execute("SELECT * FROM expenses ORDER BY expense_date DESC").fetchall()
    categories = ["Food Supplies", "Maintenance", "Utilities", "Salaries", "Festival", "Decoration", "Other"]
    return render_template("expenses.html", expenses=expense_list, categories=categories, active="expenses")


# ---------------------------------------------------------------
# Bank statement upload + reconciliation
# ---------------------------------------------------------------
@app.route("/bank", methods=["GET", "POST"])
@login_required
def bank():
    conn = get_db()
    if request.method == "POST":
        file = request.files.get("statement_file")
        if not file or file.filename == "":
            flash("Please choose a file to upload.", "error")
            return redirect(url_for("bank"))
        filename = secure_filename(file.filename)
        filepath = os.path.join(UPLOAD_DIR, filename)
        file.save(filepath)

        try:
            rows = parse_statement_file(filepath)
        except Exception as e:
            flash(f"Could not parse file: {e}", "error")
            return redirect(url_for("bank"))

        cur = conn.execute(
            "INSERT INTO bank_statement_uploads (filename, uploaded_by, total_transactions) VALUES (?,?,?)",
            (filename, session["user_id"], len(rows)),
        )
        batch_id = cur.lastrowid
        txn_ids = []
        for r in rows:
            cur2 = conn.execute(
                """INSERT INTO bank_transactions (upload_batch_id, txn_date, description, amount, txn_type, balance)
                   VALUES (?,?,?,?,?,?)""",
                (batch_id, r["txn_date"], r["description"], r["amount"], r["txn_type"], r["balance"]),
            )
            txn_ids.append(cur2.lastrowid)
        conn.commit()

        matched = reconcile_transactions(conn, txn_ids)
        conn.execute("UPDATE bank_statement_uploads SET matched_count=? WHERE id=?", (matched, batch_id))
        conn.commit()

        flash(f"Uploaded {len(rows)} transactions — {matched} auto-matched, {len(rows)-matched} need review.", "success")
        return redirect(url_for("bank"))

    uploads = conn.execute("SELECT * FROM bank_statement_uploads ORDER BY upload_date DESC").fetchall()
    unmatched = conn.execute(
        "SELECT * FROM bank_transactions WHERE reconciled_status='Unmatched' ORDER BY txn_date DESC"
    ).fetchall()
    matched = conn.execute(
        """SELECT bt.*, d.receipt_number, don.name as donor_name, e.description as expense_desc
           FROM bank_transactions bt
           LEFT JOIN donations d ON bt.matched_donation_id = d.id
           LEFT JOIN donors don ON d.donor_id = don.id
           LEFT JOIN expenses e ON bt.matched_expense_id = e.id
           WHERE bt.reconciled_status IN ('Matched','Manual') ORDER BY bt.txn_date DESC"""
    ).fetchall()
    open_donations = conn.execute(
        "SELECT d.id, don.name, d.amount, d.donation_date FROM donations d JOIN donors don ON d.donor_id=don.id WHERE d.reconciled_bank_txn_id IS NULL AND d.amount IS NOT NULL"
    ).fetchall()
    open_expenses = conn.execute(
        "SELECT id, description, amount, expense_date FROM expenses WHERE reconciled_bank_txn_id IS NULL"
    ).fetchall()
    return render_template(
        "bank.html", uploads=uploads, unmatched=unmatched, matched=matched,
        open_donations=open_donations, open_expenses=open_expenses, active="bank",
    )


@app.route("/bank/manual_match", methods=["POST"])
@login_required
def bank_manual_match():
    conn = get_db()
    txn_id = int(request.form["txn_id"])
    match_type = request.form["match_type"]  # 'donation' or 'expense'
    match_id = int(request.form["match_id"])

    if match_type == "donation":
        conn.execute("UPDATE bank_transactions SET reconciled_status='Manual', matched_donation_id=? WHERE id=?", (match_id, txn_id))
        conn.execute("UPDATE donations SET reconciled_bank_txn_id=? WHERE id=?", (txn_id, match_id))
    else:
        conn.execute("UPDATE bank_transactions SET reconciled_status='Manual', matched_expense_id=? WHERE id=?", (match_id, txn_id))
        conn.execute("UPDATE expenses SET reconciled_bank_txn_id=? WHERE id=?", (txn_id, match_id))
    conn.commit()
    flash("Transaction linked manually.", "success")
    return redirect(url_for("bank"))


# ---------------------------------------------------------------
# Reports (printable)
# ---------------------------------------------------------------
@app.route("/reports")
@login_required
def reports_page():
    return render_template("reports.html", active="reports")


@app.route("/reports/<report_type>")
@login_required
def report_view(report_type):
    conn = get_db()
    start = request.args.get("start")
    end = request.args.get("end")
    title_map = {
        "donations": "Donation Report",
        "expenses": "Expense Report",
        "inventory": "Inventory Report",
        "food": "Food Stock Report",
        "vastra": "Deity Vastra Report",
        "reconciliation": "Bank Reconciliation Report",
    }
    data = None
    if report_type == "donations":
        data = reports_mod.donation_report(conn, start, end)
    elif report_type == "expenses":
        data = reports_mod.expense_report(conn, start, end)
    elif report_type == "inventory":
        data = reports_mod.inventory_report(conn)
    elif report_type == "food":
        data = reports_mod.food_stock_report(conn)
    elif report_type == "vastra":
        data = reports_mod.vastra_report(conn)
    elif report_type == "reconciliation":
        data = reports_mod.reconciliation_report(conn)
    else:
        return "Unknown report", 404

    return render_template(
        f"report_{report_type}.html", data=data, title=title_map[report_type],
        start=start, end=end, generated_on=datetime.now().strftime("%d-%b-%Y %H:%M"),
    )


# ---------------------------------------------------------------
# Monthly Subscriptions — recurring seva plans, invoice + payment-link flow
# ---------------------------------------------------------------
PLAN_PRESETS = ["Monthly Annadaan Seva", "Monthly Mahaprasad Seva", "Monthly Deepa Seva",
                "Quarterly Vastra Seva", "Yearly Nitya Seva"]


def _send_sms_stub(mobile, message):
    """
    STUB — real SMS/WhatsApp sending goes here.
    Swap this for a real provider call, e.g.:

        from twilio.rest import Client
        client = Client(ACCOUNT_SID, AUTH_TOKEN)
        client.messages.create(to=f"+91{mobile}", from_=TWILIO_NUMBER, body=message)

    Needs a Twilio (or similar) account, API keys, and outbound internet
    access — none of which this sandbox has. Everything upstream of this
    call (invoice creation, token generation, status tracking) is fully
    real and working; only this one function is mocked.
    """
    print(f"[SMS STUB] To {mobile}: {message}")
    return True


def _send_email_stub(email, subject, message):
    """
    STUB — real email sending goes here.
    Swap this for a real provider call, e.g.:

        import smtplib
        from email.mime.text import MIMEText
        msg = MIMEText(message)
        msg["Subject"] = subject
        msg["From"] = "no-reply@yourtemple.org"
        msg["To"] = email
        with smtplib.SMTP_SSL("smtp.yourprovider.com", 465) as server:
            server.login(SMTP_USER, SMTP_PASS)
            server.send_message(msg)

    Or use a transactional email API (SendGrid/SES/Postmark) — any of
    which need real credentials and outbound internet, unavailable here.
    """
    print(f"[EMAIL STUB] To {email}: Subject: {subject} | {message}")
    return True


def _notify_invoice(conn, invoice_id):
    """Shared by both the single Send button and the bulk Send Selected action."""
    inv = conn.execute(
        "SELECT i.*, s.name, s.mobile, s.email FROM subscription_invoices i JOIN subscribers s ON i.subscriber_id=s.id WHERE i.id=?",
        (invoice_id,),
    ).fetchone()
    if not inv:
        return None

    pay_url = url_for("pay_invoice", token=inv["payment_token"], _external=True)
    message = (f"Namaskar {inv['name']}, your {inv['period_label']} seva contribution of "
               f"Rs.{inv['amount']:.0f} is due. Pay securely here: {pay_url} — Shree Jagannath Temple")
    _send_sms_stub(inv["mobile"], message)
    if inv["email"]:
        subject = f"Seva Contribution Due — {inv['invoice_number']} — Shree Jagannath Temple"
        _send_email_stub(inv["email"], subject, message)

    conn.execute(
        "UPDATE subscription_invoices SET status='Sent', notification_sent=1, notification_sent_at=? WHERE id=?",
        (datetime.now().isoformat(), invoice_id),
    )
    return inv


@app.route("/subscriptions", methods=["GET", "POST"])
@login_required
def subscriptions():
    conn = get_db()
    if request.method == "POST":
        conn.execute(
            """INSERT INTO subscribers (name, mobile, email, plan_name, plan_amount, frequency, status, start_date)
               VALUES (?,?,?,?,?,?,?,?)""",
            (request.form["name"], request.form["mobile"], request.form.get("email"),
             request.form["plan_name"], float(request.form["plan_amount"]),
             request.form.get("frequency", "Monthly"), "Active", date.today().isoformat()),
        )
        conn.commit()
        flash("Subscriber added.", "success")
        return redirect(url_for("subscriptions"))

    subs = conn.execute(
        """SELECT s.*,
             (SELECT COUNT(*) FROM subscription_invoices i WHERE i.subscriber_id=s.id AND i.status='Paid') as paid_count,
             (SELECT COUNT(*) FROM subscription_invoices i WHERE i.subscriber_id=s.id AND i.status IN ('Sent','Pending','Overdue')) as due_count
           FROM subscribers s ORDER BY s.status='Active' DESC, s.name"""
    ).fetchall()
    invoices = conn.execute(
        """SELECT i.*, s.name as subscriber_name, s.mobile, s.email FROM subscription_invoices i
           JOIN subscribers s ON i.subscriber_id = s.id
           ORDER BY (i.status='Overdue') DESC, (i.status='Sent') DESC, (i.status='Pending') DESC, i.due_date DESC"""
    ).fetchall()

    mrr = conn.execute(
        "SELECT COALESCE(SUM(plan_amount),0) FROM subscribers WHERE status='Active' AND frequency='Monthly'"
    ).fetchone()[0]
    pending_amount = conn.execute(
        "SELECT COALESCE(SUM(amount),0) FROM subscription_invoices WHERE status IN ('Sent','Pending','Overdue')"
    ).fetchone()[0]

    return render_template(
        "subscriptions.html", subs=subs, invoices=invoices, plan_presets=PLAN_PRESETS,
        mrr=mrr, pending_amount=pending_amount, active="subscriptions",
    )


@app.route("/subscriptions/<int:sub_id>/generate_invoice", methods=["POST"])
@login_required
def generate_invoice(sub_id):
    conn = get_db()
    sub = conn.execute("SELECT * FROM subscribers WHERE id=?", (sub_id,)).fetchone()
    if not sub:
        flash("Subscriber not found.", "error")
        return redirect(url_for("subscriptions"))

    year = date.today().year
    last = conn.execute(
        "SELECT invoice_number FROM subscription_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1",
        (f"INV-{year}-%",),
    ).fetchone()
    seq = int(last["invoice_number"].split("-")[-1]) + 1 if last else 1
    invoice_number = f"INV-{year}-{seq:04d}"
    token = secrets.token_urlsafe(24)
    period_label = date.today().strftime("%B %Y")

    conn.execute(
        """INSERT INTO subscription_invoices (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token)
           VALUES (?,?,?,?,?,'Pending',?)""",
        (sub_id, invoice_number, sub["plan_amount"], period_label, date.today().isoformat(), token),
    )
    conn.commit()
    flash(f"Invoice {invoice_number} created for {sub['name']}.", "success")
    return redirect(url_for("subscriptions"))


@app.route("/subscriptions/invoice/<int:invoice_id>/send", methods=["POST"])
@login_required
def send_invoice(invoice_id):
    conn = get_db()
    inv = _notify_invoice(conn, invoice_id)
    if not inv:
        flash("Invoice not found.", "error")
        return redirect(url_for("subscriptions"))
    conn.commit()
    flash(f"Invoice {inv['invoice_number']} sent to {inv['mobile']}"
          f"{' and ' + inv['email'] if inv['email'] else ''} "
          f"(simulated — see server log for the message + link).", "success")
    return redirect(url_for("subscriptions"))


@app.route("/subscriptions/bulk_send", methods=["POST"])
@login_required
def bulk_send_invoices():
    conn = get_db()
    invoice_ids = request.form.getlist("invoice_ids")
    if not invoice_ids:
        flash("No invoices selected.", "error")
        return redirect(url_for("subscriptions"))

    sent_count = 0
    for invoice_id in invoice_ids:
        inv = _notify_invoice(conn, int(invoice_id))
        if inv:
            sent_count += 1
    conn.commit()
    flash(f"Sent {sent_count} of {len(invoice_ids)} selected invoice(s) — simulated SMS/email, see server log for details.", "success")
    return redirect(url_for("subscriptions"))


@app.route("/pay/<token>")
def pay_invoice(token):
    """PUBLIC page — no login required. This is what the devotee sees after tapping the SMS link."""
    conn = get_db()
    inv = conn.execute(
        "SELECT i.*, s.name, s.mobile, s.plan_name FROM subscription_invoices i JOIN subscribers s ON i.subscriber_id=s.id WHERE i.payment_token=?",
        (token,),
    ).fetchone()
    if not inv:
        return render_template("pay_invalid.html"), 404
    return render_template("pay_invoice.html", inv=inv)


@app.route("/pay/<token>/confirm", methods=["POST"])
def pay_invoice_confirm(token):
    """
    PUBLIC — simulates the payment gateway callback.
    Real integration point: replace this handler's body with your gateway's
    webhook/callback logic (Razorpay/PayU/Stripe etc.) which verifies the
    payment signature server-side, then performs the same DB updates below.
    """
    conn = get_db()
    inv = conn.execute(
        "SELECT i.*, s.name, s.mobile, s.plan_name FROM subscription_invoices i JOIN subscribers s ON i.subscriber_id=s.id WHERE i.payment_token=?",
        (token,),
    ).fetchone()
    if not inv or inv["status"] == "Paid":
        return render_template("pay_invalid.html"), 404

    simulated_ref = "PAY-REF-" + secrets.token_hex(4).upper()
    paid_via = request.form.get("payment_method", "UPI")

    donor = conn.execute("SELECT id FROM donors WHERE phone=?", (inv["mobile"],)).fetchone()
    if not donor:
        cur = conn.execute("INSERT INTO donors (name, phone) VALUES (?,?)", (inv["name"], inv["mobile"]))
        donor_id = cur.lastrowid
    else:
        donor_id = donor["id"]

    cur = conn.execute(
        """INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_generated, created_by)
           VALUES (?,?,?,?,?,?,0,1)""",
        (donor_id, "Cash", inv["amount"], f"Subscription — {inv['plan_name']} ({inv['period_label']})",
         date.today().isoformat(), paid_via),
    )
    donation_id = cur.lastrowid

    conn.execute(
        """UPDATE subscription_invoices SET status='Paid', paid_date=?, payment_reference=?, paid_via=?, linked_donation_id=?
           WHERE id=?""",
        (datetime.now().isoformat(), simulated_ref, paid_via, donation_id, inv["id"]),
    )
    conn.commit()
    return render_template("pay_success.html", inv=inv, ref=simulated_ref)


# ---------------------------------------------------------------
# Single-page Demo Overview — everything on one shareable page
# ---------------------------------------------------------------
@app.route("/demo")
@login_required
def demo_overview():
    conn = get_db()
    summary = reports_mod.dashboard_summary(conn)

    recent_donations = conn.execute(
        """SELECT d.*, don.name as donor_name FROM donations d
           JOIN donors don ON d.donor_id = don.id
           ORDER BY d.donation_date DESC LIMIT 8"""
    ).fetchall()
    recent_invoices = conn.execute(
        """SELECT i.*, s.name as subscriber_name, s.plan_name FROM subscription_invoices i
           JOIN subscribers s ON i.subscriber_id = s.id
           ORDER BY (i.status='Overdue') DESC, (i.status='Sent') DESC, i.due_date DESC LIMIT 8"""
    ).fetchall()
    inventory_sample = conn.execute("SELECT * FROM inventory_items ORDER BY added_date DESC LIMIT 8").fetchall()
    food_sample = conn.execute("SELECT * FROM food_items ORDER BY name LIMIT 8").fetchall()
    vastra_sample = conn.execute("SELECT * FROM vastra_items ORDER BY date_added DESC LIMIT 8").fetchall()
    expense_sample = conn.execute("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 8").fetchall()
    bank_sample = conn.execute("SELECT * FROM bank_transactions ORDER BY txn_date DESC LIMIT 8").fetchall()

    return render_template(
        "demo_overview.html", summary=summary, recent_donations=recent_donations,
        recent_invoices=recent_invoices, inventory_sample=inventory_sample,
        food_sample=food_sample, vastra_sample=vastra_sample, expense_sample=expense_sample,
        bank_sample=bank_sample, generated_on=datetime.now().strftime("%d %b %Y, %I:%M %p"),
    )


# ---------------------------------------------------------------
# User management (Admin only, capped at MAX_ADMIN_USERS)
# ---------------------------------------------------------------
@app.route("/users", methods=["GET", "POST"])
@admin_required
def users():
    conn = get_db()
    if request.method == "POST":
        active_count = conn.execute("SELECT COUNT(*) FROM users WHERE is_active=1").fetchone()[0]
        if active_count >= MAX_ADMIN_USERS:
            flash(f"User limit reached ({MAX_ADMIN_USERS} active users max). Deactivate someone first.", "error")
            return redirect(url_for("users"))
        try:
            conn.execute(
                "INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)",
                (request.form["username"].strip(), generate_password_hash(request.form["password"]),
                 request.form["full_name"].strip(), request.form.get("role", "Staff")),
            )
            conn.commit()
            flash("User added.", "success")
        except Exception as e:
            flash(f"Could not add user: {e}", "error")
        return redirect(url_for("users"))

    user_list = conn.execute("SELECT * FROM users ORDER BY created_at").fetchall()
    active_count = sum(1 for u in user_list if u["is_active"])
    return render_template("users.html", users=user_list, active_count=active_count, max_users=MAX_ADMIN_USERS, active="users")


@app.route("/users/<int:user_id>/toggle", methods=["POST"])
@admin_required
def toggle_user(user_id):
    conn = get_db()
    user = conn.execute("SELECT * FROM users WHERE id=?", (user_id,)).fetchone()
    if user:
        conn.execute("UPDATE users SET is_active=? WHERE id=?", (0 if user["is_active"] else 1, user_id))
        conn.commit()
    return redirect(url_for("users"))


# ---------------------------------------------------------------
# Small JSON helper endpoints (used by the donation form's dynamic fields)
# ---------------------------------------------------------------
@app.route("/api/donors/search")
@login_required
def api_donor_search():
    q = request.args.get("q", "")
    conn = get_db()
    rows = conn.execute("SELECT id, name, phone FROM donors WHERE name LIKE ? LIMIT 10", (f"%{q}%",)).fetchall()
    return jsonify([dict(r) for r in rows])


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5055, debug=True, use_reloader=False)
