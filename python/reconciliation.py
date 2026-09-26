"""
Bank statement upload + reconciliation logic.

Accepts CSV or Excel with columns (case-insensitive, flexible naming):
  Date, Description, Amount, Type (Credit/Debit) [optional if Amount is signed], Balance [optional]

Reconciliation approach (kept deliberately simple/explainable, not a black box):
  - Credit txns are matched against donations: same amount (+/- 0.01) AND
    donation_date within +/- 3 days of the transaction date.
  - Debit txns are matched against expenses the same way.
  - First matching, not-yet-reconciled record wins. No fuzzy name matching
    yet (see README "Reconciliation logic — what's simple on purpose").
  - Anything that doesn't match is left as 'Unmatched' for manual review
    in the UI (bank_reconciliation.html lets the admin link it by hand,
    or create a new donation/expense straight from the transaction).
"""
import pandas as pd
from datetime import datetime, timedelta


def _parse_date(val):
    if isinstance(val, str):
        for fmt in ("%Y-%m-%d", "%d-%m-%Y", "%d/%m/%Y", "%m/%d/%Y", "%d %b %Y", "%d-%b-%Y"):
            try:
                return datetime.strptime(val.strip(), fmt).date()
            except ValueError:
                continue
        # let pandas try as a last resort
        return pd.to_datetime(val).date()
    if hasattr(val, "date"):
        return val.date()
    return val


def parse_statement_file(filepath):
    """Returns a list of dicts: {txn_date, description, amount, txn_type, balance}"""
    if filepath.lower().endswith(".csv"):
        df = pd.read_csv(filepath)
    else:
        df = pd.read_excel(filepath)

    # normalize column names
    df.columns = [str(c).strip().lower() for c in df.columns]
    col_map = {}
    for c in df.columns:
        if c in ("date", "txn date", "transaction date", "value date"):
            col_map[c] = "date"
        elif c in ("description", "narration", "particulars", "details"):
            col_map[c] = "description"
        elif c in ("amount", "txn amount", "transaction amount"):
            col_map[c] = "amount"
        elif c in ("credit", "credit amount", "deposit"):
            col_map[c] = "credit"
        elif c in ("debit", "debit amount", "withdrawal"):
            col_map[c] = "debit"
        elif c in ("type", "txn type", "dr/cr"):
            col_map[c] = "type"
        elif c in ("balance", "closing balance"):
            col_map[c] = "balance"
    df = df.rename(columns=col_map)

    rows = []
    for _, r in df.iterrows():
        try:
            txn_date = _parse_date(r["date"])
        except Exception:
            continue  # skip unparseable rows rather than crash the whole upload

        description = str(r.get("description", "")).strip()
        balance = r.get("balance")
        balance = float(balance) if pd.notna(balance) else None

        # Case A: separate credit/debit columns
        if "credit" in df.columns or "debit" in df.columns:
            credit = r.get("credit")
            debit = r.get("debit")
            if pd.notna(credit) and float(credit) > 0:
                amount, txn_type = float(credit), "Credit"
            elif pd.notna(debit) and float(debit) > 0:
                amount, txn_type = float(debit), "Debit"
            else:
                continue
        # Case B: single amount column, possibly signed, with/without explicit type
        else:
            amt_raw = r.get("amount")
            if pd.isna(amt_raw):
                continue
            amount = float(amt_raw)
            if "type" in df.columns and pd.notna(r.get("type")):
                type_str = str(r["type"]).strip().lower()
                txn_type = "Credit" if type_str.startswith(("cr", "credit", "deposit")) else "Debit"
                amount = abs(amount)
            else:
                txn_type = "Credit" if amount >= 0 else "Debit"
                amount = abs(amount)

        rows.append({
            "txn_date": txn_date.isoformat(),
            "description": description,
            "amount": amount,
            "txn_type": txn_type,
            "balance": balance,
        })
    return rows


def reconcile_transactions(conn, transaction_ids):
    """
    Attempts auto-matching for the given bank_transactions.id list.
    Updates reconciled_status / matched_donation_id / matched_expense_id in place.
    Returns count matched.
    """
    matched_count = 0
    for txn_id in transaction_ids:
        txn = conn.execute("SELECT * FROM bank_transactions WHERE id=?", (txn_id,)).fetchone()
        if not txn:
            continue
        txn_date = datetime.fromisoformat(txn["txn_date"]).date()
        window_start = (txn_date - timedelta(days=3)).isoformat()
        window_end = (txn_date + timedelta(days=3)).isoformat()

        if txn["txn_type"] == "Credit":
            match = conn.execute(
                """SELECT id FROM donations
                   WHERE reconciled_bank_txn_id IS NULL
                     AND amount IS NOT NULL
                     AND ABS(amount - ?) < 0.01
                     AND donation_date BETWEEN ? AND ?
                   ORDER BY donation_date LIMIT 1""",
                (txn["amount"], window_start, window_end),
            ).fetchone()
            if match:
                conn.execute(
                    "UPDATE bank_transactions SET reconciled_status='Matched', matched_donation_id=? WHERE id=?",
                    (match["id"], txn_id),
                )
                conn.execute(
                    "UPDATE donations SET reconciled_bank_txn_id=? WHERE id=?",
                    (txn_id, match["id"]),
                )
                matched_count += 1
        else:
            match = conn.execute(
                """SELECT id FROM expenses
                   WHERE reconciled_bank_txn_id IS NULL
                     AND ABS(amount - ?) < 0.01
                     AND expense_date BETWEEN ? AND ?
                   ORDER BY expense_date LIMIT 1""",
                (txn["amount"], window_start, window_end),
            ).fetchone()
            if match:
                conn.execute(
                    "UPDATE bank_transactions SET reconciled_status='Matched', matched_expense_id=? WHERE id=?",
                    (match["id"], txn_id),
                )
                conn.execute(
                    "UPDATE expenses SET reconciled_bank_txn_id=? WHERE id=?",
                    (txn_id, match["id"]),
                )
                matched_count += 1
    conn.commit()
    return matched_count
