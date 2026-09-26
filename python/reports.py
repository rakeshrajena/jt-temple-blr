"""Aggregation queries backing the Reports tab and the Dashboard summary cards."""


def dashboard_summary(conn):
    total_donations = conn.execute(
        "SELECT COALESCE(SUM(amount),0) FROM donations WHERE amount IS NOT NULL"
    ).fetchone()[0]
    total_expenses = conn.execute("SELECT COALESCE(SUM(amount),0) FROM expenses").fetchone()[0]
    donor_count = conn.execute("SELECT COUNT(DISTINCT donor_id) FROM donations").fetchone()[0]
    pending_receipts = conn.execute(
        "SELECT COUNT(*) FROM donations WHERE receipt_generated=0"
    ).fetchone()[0]
    low_stock = conn.execute(
        "SELECT COUNT(*) FROM food_items WHERE current_stock <= minimum_threshold"
    ).fetchone()[0]
    unmatched_txns = conn.execute(
        "SELECT COUNT(*) FROM bank_transactions WHERE reconciled_status='Unmatched'"
    ).fetchone()[0]
    inventory_count = conn.execute("SELECT COUNT(*) FROM inventory_items").fetchone()[0]
    vastra_count = conn.execute("SELECT COALESCE(SUM(quantity),0) FROM vastra_items").fetchone()[0]
    active_subscribers = conn.execute("SELECT COUNT(*) FROM subscribers WHERE status='Active'").fetchone()[0]
    pending_invoices = conn.execute(
        "SELECT COUNT(*) FROM subscription_invoices WHERE status IN ('Sent','Pending','Overdue')"
    ).fetchone()[0]
    mrr = conn.execute(
        "SELECT COALESCE(SUM(plan_amount),0) FROM subscribers WHERE status='Active' AND frequency='Monthly'"
    ).fetchone()[0]

    return {
        "total_donations": total_donations,
        "total_expenses": total_expenses,
        "net_balance": total_donations - total_expenses,
        "donor_count": donor_count,
        "pending_receipts": pending_receipts,
        "low_stock": low_stock,
        "unmatched_txns": unmatched_txns,
        "inventory_count": inventory_count,
        "vastra_count": vastra_count,
        "active_subscribers": active_subscribers,
        "pending_invoices": pending_invoices,
        "mrr": mrr,
    }


def donation_report(conn, start=None, end=None):
    q = """SELECT d.id, don.name as donor_name, d.donation_type, d.amount, d.purpose,
                  d.donation_date, d.payment_mode, d.receipt_number, d.receipt_generated
           FROM donations d JOIN donors don ON d.donor_id = don.id WHERE 1=1"""
    params = []
    if start:
        q += " AND d.donation_date >= ?"
        params.append(start)
    if end:
        q += " AND d.donation_date <= ?"
        params.append(end)
    q += " ORDER BY d.donation_date DESC"
    return conn.execute(q, params).fetchall()


def expense_report(conn, start=None, end=None):
    q = "SELECT * FROM expenses WHERE 1=1"
    params = []
    if start:
        q += " AND expense_date >= ?"
        params.append(start)
    if end:
        q += " AND expense_date <= ?"
        params.append(end)
    q += " ORDER BY expense_date DESC"
    return conn.execute(q, params).fetchall()


def inventory_report(conn):
    return conn.execute("SELECT * FROM inventory_items ORDER BY category, name").fetchall()


def food_stock_report(conn):
    return conn.execute("SELECT * FROM food_items ORDER BY name").fetchall()


def vastra_report(conn):
    return conn.execute("SELECT * FROM vastra_items ORDER BY deity_name, item_name").fetchall()


def reconciliation_report(conn):
    return conn.execute(
        """SELECT bt.*, u.filename, u.upload_date,
                  d.receipt_number as donation_receipt, don.name as donor_name,
                  e.description as expense_description, e.category as expense_category
           FROM bank_transactions bt
           JOIN bank_statement_uploads u ON bt.upload_batch_id = u.id
           LEFT JOIN donations d ON bt.matched_donation_id = d.id
           LEFT JOIN donors don ON d.donor_id = don.id
           LEFT JOIN expenses e ON bt.matched_expense_id = e.id
           ORDER BY bt.txn_date DESC"""
    ).fetchall()
