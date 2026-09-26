<?php
declare(strict_types=1);

function financial_year_label(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Invalid date.');
    }
    $year = (int) $dt->format('Y');
    $startYear = (int) $dt->format('n') >= 4 ? $year : $year - 1;
    return $startYear . '-' . ($startYear + 1);
}

function financial_year_start(string $label): string
{
    if (preg_match('/^(\d{4})-(\d{4})$/', $label, $m) !== 1 || (int) $m[2] !== (int) $m[1] + 1) {
        throw new InvalidArgumentException('Invalid financial year.');
    }
    return $m[1] . '-04-01';
}

function financial_year_end(string $label): string
{
    $start = financial_year_start($label);
    $year = (int) substr($start, 0, 4);
    return ($year + 1) . '-03-31';
}

function cash_book_range_error(string $from, string $to): ?string
{
    if (valid_book_date($from) === null || valid_book_date($to) === null) {
        return 'Enter a valid start and end date.';
    }
    if ($from > $to) {
        return 'The start date must be on or before the end date.';
    }
    if (financial_year_label($from) !== financial_year_label($to)) {
        return 'Choose dates inside one financial year (April to March).';
    }
    return null;
}

function valid_book_date(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function book_account(string $paymentMode): ?string
{
    return match ($paymentMode) {
        'Cash' => 'cash',
        'Bank Transfer', 'UPI', 'Cheque', 'Card', 'Netbanking' => 'bank',
        default => null,
    };
}

function validate_opening_amounts(float $cash, float $bank): ?string
{
    if ($cash < 0 || $bank < 0) {
        return 'Opening balances cannot be negative.';
    }
    if ($cash > 99999999.99 || $bank > 99999999.99) {
        return 'Opening balances are too large.';
    }
    return null;
}

function validate_contra(string $direction, float $amount, string $date): ?string
{
    if ($direction !== 'Deposit' && $direction !== 'Withdraw') {
        return 'Choose deposit or withdraw.';
    }
    if ($amount <= 0) {
        return 'Enter an amount greater than zero.';
    }
    if ($amount > 99999999.99) {
        return 'That amount is too large.';
    }
    if (valid_book_date($date) === null) {
        return 'Enter a valid date.';
    }
    return null;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function donation_movement(array $row): ?array
{
    $account = book_account((string) ($row['payment_mode'] ?? ''));
    $amount = round((float) ($row['amount'] ?? 0), 2);
    if ($account === null || $amount <= 0) {
        return null;
    }
    $particulars = 'Donation — ' . trim((string) ($row['donor_name'] ?? 'Donor'));
    $purpose = trim((string) ($row['purpose'] ?? ''));
    if ($purpose !== '') {
        $particulars .= ' (' . $purpose . ')';
    }
    $receipt = trim((string) ($row['receipt_number'] ?? ''));
    if ($receipt !== '') {
        $particulars .= ' ' . $receipt;
    }
    $trail = payment_trail($row);
    if ($trail !== '') {
        $particulars .= ' · ' . $trail;
    }
    return book_movement(
        (string) $row['donation_date'],
        1,
        (int) ($row['id'] ?? 0),
        $particulars,
        'donation',
        (string) ($row['entered_by_name'] ?? ''),
        $account === 'cash' ? $amount : 0.0,
        $account === 'bank' ? $amount : 0.0,
        0.0,
        0.0
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function expense_movement(array $row): ?array
{
    $account = book_account((string) ($row['payment_mode'] ?? ''));
    $amount = round((float) ($row['amount'] ?? 0), 2);
    if ($account === null || $amount <= 0) {
        return null;
    }
    $particulars = 'Expense — ' . trim((string) ($row['category'] ?? 'Expense'));
    $paidTo = trim((string) ($row['paid_to'] ?? ''));
    if ($paidTo !== '') {
        $particulars .= ' — ' . $paidTo;
    }
    $trail = payment_trail($row);
    if ($trail !== '') {
        $particulars .= ' · ' . $trail;
    }
    return book_movement(
        (string) $row['expense_date'],
        2,
        (int) ($row['id'] ?? 0),
        $particulars,
        'expense',
        (string) ($row['entered_by_name'] ?? ''),
        0.0,
        0.0,
        $account === 'cash' ? $amount : 0.0,
        $account === 'bank' ? $amount : 0.0
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function contra_movement(array $row): ?array
{
    $direction = (string) ($row['direction'] ?? '');
    $amount = round((float) ($row['amount'] ?? 0), 2);
    if (($direction !== 'Deposit' && $direction !== 'Withdraw') || $amount <= 0) {
        return null;
    }
    $note = trim((string) ($row['note'] ?? ''));
    $particulars = $direction === 'Deposit' ? 'Cash deposited in bank' : 'Cash withdrawn from bank';
    if ($note !== '') {
        $particulars .= ' — ' . $note;
    }
    return book_movement(
        (string) $row['entry_date'],
        3,
        (int) ($row['id'] ?? 0),
        $particulars,
        'contra',
        (string) ($row['entered_by_name'] ?? ''),
        $direction === 'Withdraw' ? $amount : 0.0,
        $direction === 'Deposit' ? $amount : 0.0,
        $direction === 'Deposit' ? $amount : 0.0,
        $direction === 'Withdraw' ? $amount : 0.0
    );
}

/**
 * @param list<array<string, mixed>> $movements
 * @return array{
 *   financial_year: string,
 *   from: string,
 *   to: string,
 *   opening_cash: float,
 *   opening_bank: float,
 *   closing_cash: float,
 *   closing_bank: float,
 *   lines: list<array<string, mixed>>
 * }
 */
function build_cash_book(array $movements, string $from, string $to, float $yearCash, float $yearBank): array
{
    $error = cash_book_range_error($from, $to);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }
    $fy = financial_year_label($from);
    $fyStart = financial_year_start($fy);
    $openingCash = round($yearCash, 2);
    $openingBank = round($yearBank, 2);
    $lines = [];
    foreach ($movements as $movement) {
        $date = (string) $movement['date'];
        if ($date < $fyStart || $date > $to) {
            continue;
        }
        if ($date < $from) {
            $openingCash = round($openingCash + (float) $movement['receipt_cash'] - (float) $movement['payment_cash'], 2);
            $openingBank = round($openingBank + (float) $movement['receipt_bank'] - (float) $movement['payment_bank'], 2);
            continue;
        }
        $lines[] = $movement;
    }
    usort($lines, static function (array $a, array $b): int {
        return [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']];
    });
    $closingCash = $openingCash;
    $closingBank = $openingBank;
    foreach ($lines as $line) {
        $closingCash = round($closingCash + (float) $line['receipt_cash'] - (float) $line['payment_cash'], 2);
        $closingBank = round($closingBank + (float) $line['receipt_bank'] - (float) $line['payment_bank'], 2);
    }
    return [
        'financial_year' => $fy,
        'from' => $from,
        'to' => $to,
        'opening_cash' => $openingCash,
        'opening_bank' => $openingBank,
        'closing_cash' => $closingCash,
        'closing_bank' => $closingBank,
        'lines' => $lines,
    ];
}

/**
 * @param list<array<string, mixed>> $lines
 * @return list<array{date: string, particulars: string, entry: string, amount: float, entered_by: string}>
 */
function build_day_book(array $lines): array
{
    $rows = [];
    foreach ($lines as $line) {
        if ($line['kind'] === 'contra') {
            $rows[] = [
                'date' => (string) $line['date'],
                'particulars' => (string) $line['particulars'],
                'entry' => 'Contra',
                'amount' => round(max(
                    (float) $line['receipt_cash'],
                    (float) $line['receipt_bank'],
                    (float) $line['payment_cash'],
                    (float) $line['payment_bank']
                ), 2),
                'entered_by' => (string) $line['entered_by'],
            ];
            continue;
        }
        $receipt = (float) $line['receipt_cash'] + (float) $line['receipt_bank'];
        $payment = (float) $line['payment_cash'] + (float) $line['payment_bank'];
        $isCash = (float) $line['receipt_cash'] > 0 || (float) $line['payment_cash'] > 0;
        $rows[] = [
            'date' => (string) $line['date'],
            'particulars' => (string) $line['particulars'],
            'entry' => ($receipt > 0 ? 'Receipt' : 'Payment') . ' · ' . ($isCash ? 'Cash' : 'Bank'),
            'amount' => round($receipt > 0 ? $receipt : $payment, 2),
            'entered_by' => (string) $line['entered_by'],
        ];
    }
    return $rows;
}

/** @return list<array<string, mixed>> */
function load_book_movements(string $from, string $to): array
{
    $fyStart = financial_year_start(financial_year_label($from));
    $movements = [];
    $donations = db_all(
        'SELECT d.id, d.donation_date, d.amount, d.payment_mode, d.purpose, d.receipt_number,
                don.name AS donor_name, u.full_name AS entered_by_name
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         LEFT JOIN users u ON u.id = d.created_by
         WHERE d.donation_date BETWEEN ? AND ?',
        [$fyStart, $to]
    );
    foreach ($donations as $row) {
        $movement = donation_movement($row);
        if ($movement !== null) {
            $movements[] = $movement;
        }
    }
    $expenses = db_all(
        'SELECT e.id, e.expense_date, e.amount, e.payment_mode, e.category, e.paid_to,
                u.full_name AS entered_by_name
         FROM expenses e
         JOIN approvals ap ON ap.subject_type = \'expense\' AND ap.subject_id = e.id AND ap.status = \'Approved\'
         LEFT JOIN users u ON u.id = e.added_by
         WHERE e.expense_date BETWEEN ? AND ?',
        [$fyStart, $to]
    );
    foreach ($expenses as $row) {
        $movement = expense_movement($row);
        if ($movement !== null) {
            $movements[] = $movement;
        }
    }
    $contras = db_all(
        'SELECT c.id, c.entry_date, c.direction, c.amount, c.note, u.full_name AS entered_by_name
         FROM contra_entries c
         JOIN approvals apc ON apc.subject_type = \'contra\' AND apc.subject_id = c.id AND apc.status = \'Approved\'
         LEFT JOIN users u ON u.id = c.entered_by
         WHERE c.entry_date BETWEEN ? AND ?',
        [$fyStart, $to]
    );
    foreach ($contras as $row) {
        $movement = contra_movement($row);
        if ($movement !== null) {
            $movements[] = $movement;
        }
    }
    return $movements;
}

/** @return array{cash: float, bank: float, note: string} */
function load_opening_balance(string $financialYear): array
{
    $row = db_one(
        'SELECT o.cash_amount, o.bank_amount, o.note
         FROM opening_balances o
         JOIN approvals a ON a.subject_type = \'opening\' AND a.subject_id = o.id AND a.status = \'Approved\'
         WHERE o.financial_year = ?',
        [$financialYear]
    );
    if ($row === null) {
        return ['cash' => 0.0, 'bank' => 0.0, 'note' => ''];
    }
    return [
        'cash' => round((float) $row['cash_amount'], 2),
        'bank' => round((float) $row['bank_amount'], 2),
        'note' => (string) ($row['note'] ?? ''),
    ];
}

function default_ledger_range(?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $label = financial_year_label($today);
    $end = financial_year_end($label);
    return [
        'from' => financial_year_start($label),
        'to' => $today > $end ? $end : $today,
    ];
}

function ledger_head_name(string $raw, string $fallback): string
{
    $name = trim($raw);
    return $name === '' ? $fallback : $name;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function donation_ledger_line(array $row): ?array
{
    $amount = round((float) ($row['amount'] ?? 0), 2);
    if (book_account((string) ($row['payment_mode'] ?? '')) === null || $amount <= 0) {
        return null;
    }
    $particulars = 'Donation — ' . trim((string) ($row['donor_name'] ?? 'Donor'));
    $receipt = trim((string) ($row['receipt_number'] ?? ''));
    if ($receipt !== '') {
        $particulars .= ' ' . $receipt;
    }
    return [
        'head' => ledger_head_name((string) ($row['purpose'] ?? ''), 'General'),
        'date' => (string) $row['donation_date'],
        'sort' => 100000000 + (int) ($row['id'] ?? 0),
        'particulars' => $particulars,
        'entered_by' => (string) ($row['entered_by_name'] ?? ''),
        'received' => $amount,
        'spent' => 0.0,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function expense_ledger_line(array $row): ?array
{
    $amount = round((float) ($row['amount'] ?? 0), 2);
    if (book_account((string) ($row['payment_mode'] ?? '')) === null || $amount <= 0) {
        return null;
    }
    $particulars = 'Expense';
    $paidTo = trim((string) ($row['paid_to'] ?? ''));
    if ($paidTo !== '') {
        $particulars .= ' — ' . $paidTo;
    }
    $trail = payment_trail($row);
    if ($trail !== '') {
        $particulars .= ' · ' . $trail;
    }
    return [
        'head' => ledger_head_name((string) ($row['category'] ?? ''), 'Other'),
        'date' => (string) $row['expense_date'],
        'sort' => 200000000 + (int) ($row['id'] ?? 0),
        'particulars' => $particulars,
        'entered_by' => (string) ($row['entered_by_name'] ?? ''),
        'received' => 0.0,
        'spent' => $amount,
    ];
}

/**
 * @param list<array<string, mixed>> $lines
 * @return list<array<string, mixed>>
 */
function build_ledgers(array $lines, string $from, string $to): array
{
    $error = cash_book_range_error($from, $to);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }
    $fyStart = financial_year_start(financial_year_label($from));
    $heads = [];
    foreach ($lines as $line) {
        $date = (string) $line['date'];
        if ($date < $fyStart || $date > $to) {
            continue;
        }
        $name = (string) $line['head'];
        if (!isset($heads[$name])) {
            $heads[$name] = [
                'head' => $name,
                'opening' => 0.0,
                'received' => 0.0,
                'spent' => 0.0,
                'balance' => 0.0,
                'lines' => [],
            ];
        }
        if ($date < $from) {
            $heads[$name]['opening'] = round(
                $heads[$name]['opening'] + (float) $line['received'] - (float) $line['spent'],
                2
            );
            continue;
        }
        $heads[$name]['lines'][] = $line;
        $heads[$name]['received'] = round($heads[$name]['received'] + (float) $line['received'], 2);
        $heads[$name]['spent'] = round($heads[$name]['spent'] + (float) $line['spent'], 2);
    }
    foreach ($heads as &$head) {
        usort($head['lines'], static function (array $a, array $b): int {
            return [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']];
        });
        $running = $head['opening'];
        foreach ($head['lines'] as &$line) {
            $running = round($running + (float) $line['received'] - (float) $line['spent'], 2);
            $line['balance'] = $running;
        }
        unset($line);
        $head['balance'] = round($head['opening'] + $head['received'] - $head['spent'], 2);
    }
    unset($head);
    ksort($heads, SORT_FLAG_CASE | SORT_NATURAL);
    return array_values($heads);
}

/** @return list<array<string, mixed>> */
function load_ledger_lines(string $from, string $to): array
{
    $fyStart = financial_year_start(financial_year_label($from));
    $lines = [];
    $donations = db_all(
        'SELECT d.id, d.donation_date, d.amount, d.payment_mode, d.purpose, d.receipt_number,
                don.name AS donor_name, u.full_name AS entered_by_name
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         LEFT JOIN users u ON u.id = d.created_by
         WHERE d.donation_date BETWEEN ? AND ?',
        [$fyStart, $to]
    );
    foreach ($donations as $row) {
        $line = donation_ledger_line($row);
        if ($line !== null) {
            $lines[] = $line;
        }
    }
    $expenses = db_all(
        'SELECT e.id, e.expense_date, e.amount, e.payment_mode, e.category, e.paid_to,
                u.full_name AS entered_by_name
         FROM expenses e
         JOIN approvals ap ON ap.subject_type = \'expense\' AND ap.subject_id = e.id AND ap.status = \'Approved\'
         LEFT JOIN users u ON u.id = e.added_by
         WHERE e.expense_date BETWEEN ? AND ?',
        [$fyStart, $to]
    );
    foreach ($expenses as $row) {
        $line = expense_ledger_line($row);
        if ($line !== null) {
            $lines[] = $line;
        }
    }
    return $lines;
}

function default_book_range(?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $fy = financial_year_label($today);
    $monthStart = substr($today, 0, 8) . '01';
    $from = $monthStart < financial_year_start($fy) ? financial_year_start($fy) : $monthStart;
    $to = $today > financial_year_end($fy) ? financial_year_end($fy) : $today;
    return ['from' => $from, 'to' => $to];
}

/** @return array<string, mixed> */
function book_movement(
    string $date,
    int $kindOrder,
    int $id,
    string $particulars,
    string $kind,
    string $enteredBy,
    float $receiptCash,
    float $receiptBank,
    float $paymentCash,
    float $paymentBank
): array {
    return [
        'date' => $date,
        'sort' => ($kindOrder * 100000000) + $id,
        'particulars' => $particulars,
        'kind' => $kind,
        'entered_by' => $enteredBy,
        'receipt_cash' => $receiptCash,
        'receipt_bank' => $receiptBank,
        'payment_cash' => $paymentCash,
        'payment_bank' => $paymentBank,
    ];
}
