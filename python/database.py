"""
Database layer for the Temple Admin app.

Runs on SQLite for local development/demo (zero-setup, file-based).
Table and column names are kept IDENTICAL to schema_mysql.sql so that
moving to MySQL in production is a connection-layer swap, not a rewrite.
See README.md -> "Moving to MySQL in Production".
"""
import sqlite3
import os
import secrets
from datetime import date, timedelta
from werkzeug.security import generate_password_hash

DB_PATH = os.path.join(os.path.dirname(__file__), "temple.db")


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


SCHEMA = """
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'Staff',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    quantity INTEGER NOT NULL DEFAULT 0,
    unit TEXT DEFAULT 'pcs',
    item_condition TEXT DEFAULT 'Good',
    location TEXT,
    source TEXT DEFAULT 'Purchased',
    donation_id INTEGER,
    added_date TEXT NOT NULL,
    added_by INTEGER,
    notes TEXT,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS food_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    unit TEXT NOT NULL DEFAULT 'kg',
    current_stock REAL NOT NULL DEFAULT 0,
    minimum_threshold REAL DEFAULT 0,
    last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS food_usage_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    food_item_id INTEGER NOT NULL,
    txn_type TEXT NOT NULL,
    quantity REAL NOT NULL,
    purpose TEXT,
    txn_date TEXT NOT NULL,
    logged_by INTEGER,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS vastra_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    deity_name TEXT NOT NULL,
    item_name TEXT NOT NULL,
    color TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    source TEXT DEFAULT 'Purchased',
    donation_id INTEGER,
    date_added TEXT NOT NULL,
    status TEXT DEFAULT 'In Store',
    notes TEXT
);

CREATE TABLE IF NOT EXISTS donors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    address TEXT,
    pan_number TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS donations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    donor_id INTEGER NOT NULL,
    donation_type TEXT NOT NULL,
    amount REAL,
    linked_food_id INTEGER,
    linked_vastra_id INTEGER,
    linked_inventory_id INTEGER,
    purpose TEXT,
    donation_date TEXT NOT NULL,
    payment_mode TEXT NOT NULL,
    receipt_number TEXT UNIQUE,
    receipt_generated INTEGER DEFAULT 0,
    reconciled_bank_txn_id INTEGER,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    description TEXT,
    amount REAL NOT NULL,
    paid_to TEXT,
    expense_date TEXT NOT NULL,
    payment_mode TEXT NOT NULL,
    reconciled_bank_txn_id INTEGER,
    receipt_ref TEXT,
    added_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bank_statement_uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT,
    upload_date TEXT DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INTEGER,
    total_transactions INTEGER DEFAULT 0,
    matched_count INTEGER DEFAULT 0,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bank_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    upload_batch_id INTEGER NOT NULL,
    txn_date TEXT NOT NULL,
    description TEXT,
    amount REAL NOT NULL,
    txn_type TEXT NOT NULL,
    balance REAL,
    reconciled_status TEXT DEFAULT 'Unmatched',
    matched_donation_id INTEGER,
    matched_expense_id INTEGER,
    FOREIGN KEY (upload_batch_id) REFERENCES bank_statement_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_donation_id) REFERENCES donations(id),
    FOREIGN KEY (matched_expense_id) REFERENCES expenses(id)
);

CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    donation_id INTEGER NOT NULL,
    receipt_number TEXT NOT NULL UNIQUE,
    generated_date TEXT DEFAULT CURRENT_TIMESTAMP,
    generated_by INTEGER,
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    mobile TEXT NOT NULL UNIQUE,
    email TEXT,
    plan_name TEXT NOT NULL DEFAULT 'Monthly Seva',
    plan_amount REAL NOT NULL,
    frequency TEXT NOT NULL DEFAULT 'Monthly',
    status TEXT NOT NULL DEFAULT 'Active',
    start_date TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscription_invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscriber_id INTEGER NOT NULL,
    invoice_number TEXT NOT NULL UNIQUE,
    amount REAL NOT NULL,
    period_label TEXT,
    due_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pending',
    payment_token TEXT NOT NULL UNIQUE,
    notification_sent INTEGER DEFAULT 0,
    notification_sent_at TEXT,
    paid_date TEXT,
    paid_via TEXT,
    payment_reference TEXT,
    linked_donation_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id),
    FOREIGN KEY (linked_donation_id) REFERENCES donations(id)
);

CREATE TABLE IF NOT EXISTS food_coupon_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    coupon_name TEXT NOT NULL,
    cost REAL NOT NULL,
    start_sl_no INTEGER NOT NULL,
    end_sl_no INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    total_value REAL NOT NULL,
    created_date TEXT NOT NULL,
    created_by INTEGER,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
"""


def init_db(seed=True):
    fresh = not os.path.exists(DB_PATH)
    conn = get_db()
    conn.executescript(SCHEMA)
    conn.commit()

    if fresh and seed:
        _seed(conn)
    conn.close()


def _seed(conn):
    cur = conn.cursor()

    # --- Users (admin + a couple of staff, well under the 10-user cap) ---
    users = [
        ("admin", "temple@123", "Temple Administrator", "Admin"),
        ("ramesh", "ramesh@123", "Ramesh Patra (Trustee)", "Admin"),
        ("staff1", "staff@123", "Suresh (Store Keeper)", "Staff"),
    ]
    for uname, pwd, full, role in users:
        cur.execute(
            "INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)",
            (uname, generate_password_hash(pwd), full, role),
        )
    conn.commit()

    # --- Donors ---
    donors = [
        ("Bikash Mohanty", "9861012345", "bikash.m@example.com", "Bhubaneswar", "ABCDE1234F"),
        ("Sujata Nayak", "9437098765", "sujata.n@example.com", "Sarjapura, Bengaluru", None),
        ("Anil Kumar Sahoo", "9090911223", None, "Cuttack", "PQRSX5678K"),
        ("Meera Panda", "8895671234", "meera.p@example.com", "Bengaluru", None),
        ("Debashish Pattnaik", "9776655443", None, "Bhubaneswar", None),
        ("Ranjit Kumar Behera", "9845123456", "ranjit.b@example.com", "Whitefield, Bengaluru", "LMNOP9876Q"),
        ("Swagatika Rout", "9900112233", None, "HSR Layout, Bengaluru", None),
        ("Prakash Chandra Dash", "8867234561", "prakash.d@example.com", "Bhubaneswar", "XYZAB4321C"),
        ("Kabita Jena", "9778345612", None, "Sarjapura, Bengaluru", None),
        ("Manoj Kumar Swain", "9556781234", "manoj.s@example.com", "Cuttack", None),
    ]
    for name, phone, email, addr, pan in donors:
        cur.execute(
            "INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)",
            (name, phone, email, addr, pan),
        )
    conn.commit()

    # --- Inventory items (hardware, equipment, puja items) ---
    today = date.today()
    inventory = [
        ("Kitchen Equipment", "Commercial Gas Stove (4-burner)", "For Mahaprasad kitchen", 2, "pcs", "Good", "Kitchen", "Purchased", today - timedelta(days=180)),
        ("Kitchen Equipment", "Large Cooking Vessels (Handi)", "50L capacity", 6, "pcs", "Good", "Kitchen", "Purchased", today - timedelta(days=180)),
        ("Electronics", "Microphone & PA System", "For aarti and announcements", 1, "set", "Good", "Sanctum", "Donated", today - timedelta(days=90)),
        ("Electronics", "LED Flood Lights", "Courtyard lighting", 8, "pcs", "Good", "Courtyard", "Purchased", today - timedelta(days=60)),
        ("Furniture", "Plastic Chairs", "For events and gatherings", 150, "pcs", "Good", "Store Room", "Purchased", today - timedelta(days=120)),
        ("Furniture", "Folding Tables", "For prasad distribution", 10, "pcs", "Fair", "Store Room", "Purchased", today - timedelta(days=200)),
        ("Puja Items", "Brass Lamps (Diya Stand)", "Large ceremonial lamps", 4, "pcs", "Good", "Sanctum", "Donated", today - timedelta(days=45)),
        ("Puja Items", "Bell Metal Bells", "Temple bells", 3, "pcs", "New", "Sanctum", "Donated", today - timedelta(days=15)),
        ("Decoration", "Marigold Garland Hooks", "Ceiling mounting hooks", 40, "pcs", "Good", "Store Room", "Purchased", today - timedelta(days=30)),
        ("Decoration", "LED String Lights", "Festival decoration", 25, "sets", "Good", "Store Room", "Purchased", today - timedelta(days=25)),
        ("Hardware", "Extension Boards", "Power distribution", 12, "pcs", "Good", "Store Room", "Purchased", today - timedelta(days=90)),
        ("Hardware", "Ladders", "Maintenance work", 2, "pcs", "Fair", "Store Room", "Purchased", today - timedelta(days=300)),
        ("Kitchen Equipment", "Steel Plates & Bowls", "For prasad serving", 500, "pcs", "Good", "Kitchen", "Donated", today - timedelta(days=10)),
        ("Electronics", "CCTV Camera System", "4-camera security setup", 1, "set", "New", "Main Gate", "Purchased", today - timedelta(days=7)),
        ("Furniture", "Steel Almirah", "Document & valuables storage", 2, "pcs", "Good", "Office", "Purchased", today - timedelta(days=150)),
    ]
    for category, name, desc, qty, unit, cond, loc, source, d in inventory:
        cur.execute(
            """INSERT INTO inventory_items (category, name, description, quantity, unit, item_condition, location, source, added_date, added_by)
               VALUES (?,?,?,?,?,?,?,?,?,?)""",
            (category, name, desc, qty, unit, cond, loc, source, d.isoformat(), 1),
        )
    conn.commit()

    # --- Food items + usage log ---
    food = [
        ("Rice", "kg", 120, 30),
        ("Moong Dal", "kg", 45, 15),
        ("Ghee", "litre", 18, 5),
        ("Sugar", "kg", 30, 10),
        ("Vegetables (mixed)", "kg", 25, 10),
        ("Wheat Flour (Atta)", "kg", 8, 20),
        ("Milk", "litre", 40, 15),
        ("Coconut", "pcs", 60, 20),
        ("Jaggery", "kg", 4, 10),
        ("Cooking Oil", "litre", 22, 10),
    ]
    for name, unit, stock, thresh in food:
        cur.execute(
            "INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)",
            (name, unit, stock, thresh),
        )
    conn.commit()

    usage_rows = [
        (1, "Used", 8, "Daily Mahaprasad", today - timedelta(days=1)),
        (1, "Used", 8, "Daily Mahaprasad", today),
        (2, "Used", 3, "Daily Mahaprasad", today),
        (3, "Used", 1.5, "Daily Mahaprasad", today),
        (1, "Added", 50, "Purchased from local supplier", today - timedelta(days=5)),
        (4, "Used", 2, "Daily Mahaprasad", today - timedelta(days=1)),
        (5, "Used", 4, "Daily Mahaprasad", today),
        (5, "Used", 3.5, "Daily Mahaprasad", today - timedelta(days=1)),
        (6, "Used", 6, "Prasad preparation", today - timedelta(days=2)),
        (7, "Used", 5, "Daily Mahaprasad + Abhishek", today),
        (7, "Added", 20, "Donated by Ranjit Behera", today - timedelta(days=3)),
        (8, "Used", 12, "Ratha Yatra bhog", today - timedelta(days=6)),
        (9, "Used", 1, "Kheeri preparation", today - timedelta(days=2)),
        (10, "Used", 3, "Daily Mahaprasad", today - timedelta(days=1)),
        (2, "Added", 25, "Purchased from local supplier", today - timedelta(days=8)),
        (3, "Added", 10, "Donated by Swagatika Rout", today - timedelta(days=12)),
        (1, "Used", 8, "Daily Mahaprasad", today - timedelta(days=2)),
        (1, "Used", 8, "Daily Mahaprasad", today - timedelta(days=3)),
    ]
    for food_id, txn_type, qty, purpose, d in usage_rows:
        cur.execute(
            "INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)",
            (food_id, txn_type, qty, purpose, d.isoformat(), 3),
        )
    conn.commit()

    # --- Vastra items ---
    vastra = [
        ("Jagannath", "Silk Pata (Yellow)", "Yellow", 2, "Donated", today - timedelta(days=20), "In Store"),
        ("Balabhadra", "Silk Pata (Green)", "Green", 2, "Donated", today - timedelta(days=20), "In Store"),
        ("Subhadra", "Silk Pata (Red)", "Red", 2, "Purchased", today - timedelta(days=45), "In Use"),
        ("Jagannath", "Cotton Pata (Daily)", "White", 5, "Purchased", today - timedelta(days=60), "In Use"),
        ("Balabhadra", "Cotton Pata (Daily)", "White", 5, "Purchased", today - timedelta(days=60), "In Use"),
        ("Subhadra", "Cotton Pata (Daily)", "White", 5, "Purchased", today - timedelta(days=60), "In Use"),
        ("Jagannath", "Festival Silk Pata (Gold Border)", "Yellow/Gold", 1, "Donated", today - timedelta(days=5), "In Store"),
        ("Sudarshan", "Small Pata", "Red", 3, "Purchased", today - timedelta(days=90), "In Store"),
        ("Balabhadra", "Old Silk Pata (Faded)", "Green", 2, "Purchased", today - timedelta(days=400), "Retired"),
    ]
    for deity, item, color, qty, source, d, status in vastra:
        cur.execute(
            "INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status) VALUES (?,?,?,?,?,?,?)",
            (deity, item, color, qty, source, d.isoformat(), status),
        )
    conn.commit()

    # --- Donations (mix of cash + in-kind, across all donors) ---
    donations = [
        (1, "Cash", 5000, "General", today - timedelta(days=18), "UPI", "RCPT-2026-0001", 1),
        (2, "Cash", 2100, "Annadaan", today - timedelta(days=16), "Cash", "RCPT-2026-0002", 1),
        (3, "Cash", 11000, "Ratha Yatra", today - timedelta(days=15), "Bank Transfer", "RCPT-2026-0003", 1),
        (6, "Cash", 7500, "Vastra Seva", today - timedelta(days=14), "UPI", "RCPT-2026-0004", 1),
        (7, "Cash", 3000, "General", today - timedelta(days=13), "Cash", "RCPT-2026-0005", 1),
        (8, "Cash", 15000, "Construction", today - timedelta(days=12), "Bank Transfer", "RCPT-2026-0006", 1),
        (9, "Cash", 1200, "General", today - timedelta(days=11), "UPI", "RCPT-2026-0007", 1),
        (10, "Cash", 9800, "Annadaan", today - timedelta(days=9), "Bank Transfer", "RCPT-2026-0008", 1),
        (4, "Cash", 1500, "General", today - timedelta(days=3), "UPI", None, 0),
        (5, "Cash", 21000, "Annadaan", today - timedelta(days=1), "Bank Transfer", None, 0),
        (6, "Cash", 4500, "General", today - timedelta(days=7), "Cash", None, 0),
        (1, "Cash", 3100, "Ratha Yatra", today - timedelta(days=17), "UPI", None, 0),
    ]
    for donor_id, dtype, amount, purpose, d, mode, receipt, generated in donations:
        cur.execute(
            """INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
               VALUES (?,?,?,?,?,?,?,?,?)""",
            (donor_id, dtype, amount, purpose, d.isoformat(), mode, receipt, generated, 1),
        )
    conn.commit()

    # --- Expenses ---
    expenses = [
        ("Food Supplies", "Rice & Dal purchase", 8500, "Sri Balaji Traders", today - timedelta(days=8), "Bank Transfer"),
        ("Maintenance", "Electrical repair - sanctum lighting", 3200, "Local Electrician", today - timedelta(days=7), "Cash"),
        ("Festival", "Ratha Yatra decoration materials", 15600, "Utkal Decorators", today - timedelta(days=15), "Bank Transfer"),
        ("Utilities", "Electricity bill", 4200, "BESCOM", today - timedelta(days=5), "Bank Transfer"),
        ("Utilities", "Water bill", 1100, "BWSSB", today - timedelta(days=5), "Bank Transfer"),
        ("Salaries", "Priest & staff monthly salary", 42000, "Temple Staff", today - timedelta(days=4), "Bank Transfer"),
        ("Decoration", "Flowers for daily puja", 2800, "Local Flower Vendor", today - timedelta(days=1), "Cash"),
        ("Maintenance", "Plumbing repair - kitchen", 1650, "Local Plumber", today - timedelta(days=10), "Cash"),
        ("Other", "Printing - donation receipt books", 950, "Sri Sai Printers", today - timedelta(days=20), "Cash"),
    ]
    for cat, desc, amount, paid_to, d, mode in expenses:
        cur.execute(
            "INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, added_by) VALUES (?,?,?,?,?,?,?)",
            (cat, desc, amount, paid_to, d.isoformat(), mode, 1),
        )
    conn.commit()

    # --- Monthly Subscriptions (recurring seva plans) ---
    subscribers = [
        ("Bikash Mohanty", "9861012345", "bikash.m@example.com", "Monthly Annadaan Seva", 1100, "Monthly", "Active", today - timedelta(days=95)),
        ("Sujata Nayak", "9437098765", "sujata.n@example.com", "Monthly Mahaprasad Seva", 501, "Monthly", "Active", today - timedelta(days=65)),
        ("Ranjit Kumar Behera", "9845123456", "ranjit.b@example.com", "Quarterly Vastra Seva", 3000, "Quarterly", "Active", today - timedelta(days=150)),
        ("Prakash Chandra Dash", "8867234561", "prakash.d@example.com", "Monthly Annadaan Seva", 1100, "Monthly", "Paused", today - timedelta(days=200)),
        ("Kabita Jena", "9778345612", None, "Monthly Deepa Seva", 251, "Monthly", "Active", today - timedelta(days=40)),
        ("Manoj Kumar Swain", "9556781234", "manoj.s@example.com", "Yearly Nitya Seva", 12000, "Yearly", "Cancelled", today - timedelta(days=370)),
    ]
    sub_ids = []
    for name, mobile, email, plan, amount, freq, status, start in subscribers:
        cur.execute(
            """INSERT INTO subscribers (name, mobile, email, plan_name, plan_amount, frequency, status, start_date)
               VALUES (?,?,?,?,?,?,?,?)""",
            (name, mobile, email, plan, amount, freq, status, start.isoformat()),
        )
        sub_ids.append(cur.lastrowid)
    conn.commit()

    def gen_token():
        return secrets.token_urlsafe(24)

    sub_by_id = dict(zip(sub_ids, subscribers))

    # Invoices: a mix of Paid (history), Sent (awaiting payment), Pending (not yet notified), Overdue
    invoices = [
        # Bikash — 3 months history, all paid, on-time subscriber
        (sub_ids[0], 1100, "July 2026", today - timedelta(days=65), "Paid", today - timedelta(days=66), today - timedelta(days=64), "UPI", "PAY-REF-88213"),
        (sub_ids[0], 1100, "August 2026", today - timedelta(days=35), "Paid", today - timedelta(days=36), today - timedelta(days=34), "UPI", "PAY-REF-88540"),
        (sub_ids[0], 1100, "September 2026", today - timedelta(days=2), "Sent", today - timedelta(days=2), None, None, None),
        # Sujata — current invoice sent, previous paid
        (sub_ids[1], 501, "August 2026", today - timedelta(days=33), "Paid", today - timedelta(days=34), today - timedelta(days=32), "UPI", "PAY-REF-79021"),
        (sub_ids[1], 501, "September 2026", today - timedelta(days=1), "Sent", today - timedelta(days=1), None, None, None),
        # Ranjit — quarterly, one paid, next one pending (not yet sent)
        (sub_ids[2], 3000, "Q2 2026", today - timedelta(days=80), "Paid", today - timedelta(days=81), today - timedelta(days=78), "Bank Transfer", "PAY-REF-65310"),
        (sub_ids[2], 3000, "Q3 2026", today + timedelta(days=10), "Pending", None, None, None, None),
        # Prakash — paused subscriber, has an overdue invoice from before pausing
        (sub_ids[3], 1100, "June 2026", today - timedelta(days=40), "Overdue", today - timedelta(days=41), None, None, None),
        # Kabita — new subscriber, first invoice just sent
        (sub_ids[4], 251, "September 2026", today - timedelta(days=1), "Sent", today - timedelta(days=1), None, None, None),
    ]
    invoice_seq = 0
    for sub_id, amount, period, due, status, sms_sent, paid, paid_via, ref in invoices:
        invoice_seq += 1
        invoice_number = f"INV-{today.year}-{invoice_seq:04d}"
        token = gen_token()
        cur.execute(
            """INSERT INTO subscription_invoices
               (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token,
                notification_sent, notification_sent_at, paid_date, payment_reference)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)""",
            (sub_id, invoice_number, amount, period, due.isoformat(), status, token,
             1 if sms_sent else 0, sms_sent.isoformat() if sms_sent else None,
             paid.isoformat() if paid else None, ref),
        )
        invoice_id = cur.lastrowid

        # For paid invoices, mirror into donations so they show up in donation reports too
        if status == "Paid":
            sub_row = sub_by_id[sub_id]
            donor_row = cur.execute("SELECT id FROM donors WHERE phone=?", (sub_row[1],)).fetchone()
            if not donor_row:
                cur.execute(
                    "INSERT INTO donors (name, phone, email) VALUES (?,?,?)",
                    (sub_row[0], sub_row[1], sub_row[2]),
                )
                donor_id = cur.lastrowid
            else:
                donor_id = donor_row[0]
            cur.execute(
                """INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode,
                   receipt_generated, created_by) VALUES (?,?,?,?,?,?,?,?)""",
                (donor_id, "Cash", amount, f"Subscription — {sub_row[3]} ({period})", paid.isoformat() if paid else due.isoformat(),
                 paid_via or "UPI", 0, 1),
            )
            donation_id = cur.lastrowid
            cur.execute(
                "UPDATE subscription_invoices SET linked_donation_id=? WHERE id=?",
                (donation_id, invoice_id),
            )
    conn.commit()


if __name__ == "__main__":
    init_db()
    print("Database initialized at", DB_PATH)
