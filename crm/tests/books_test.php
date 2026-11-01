<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

function movement_amount(array $book, string $field): float
{
    $total = 0.0;
    foreach ($book['lines'] as $line) {
        $total += (float) $line[$field];
    }
    return round($total, 2);
}

check(financial_year_label('2026-04-01') === '2026-2027', '1 April starts the new financial year');
check(financial_year_label('2026-03-31') === '2025-2026', '31 March stays in the previous financial year');
check(financial_year_label('2026-01-15') === '2025-2026', 'January belongs to the year that started the previous April');
check(financial_year_start('2026-2027') === '2026-04-01', 'financial year starts 1 April');
check(financial_year_end('2026-2027') === '2027-03-31', 'financial year ends 31 March');
check(cash_book_range_error('2026-03-01', '2026-04-02') !== null, 'a range cannot cross 31 March');
check(cash_book_range_error('2026-05-10', '2026-05-01') !== null, 'start date cannot be after end date');
check(cash_book_range_error('2026-05-01', '2026-05-31') === null, 'a range inside one year is accepted');

check(book_account('Cash') === 'cash', 'cash mode posts to cash');
check(book_account('UPI') === 'bank', 'UPI posts to bank');
check(book_account('Bank Transfer') === 'bank', 'bank transfer posts to bank');
check(book_account('Cheque') === 'bank', 'cheque posts to bank');
check(book_account('Card') === 'bank', 'card posts to bank');
check(book_account('In-Kind') === null, 'in-kind does not enter the cash book');

check(validate_opening_amounts(-1, 0) !== null, 'negative opening cash is rejected');
check(validate_opening_amounts(0, 0) === null, 'zero opening balance is allowed');
check(validate_contra('Deposit', 0, '2026-05-01') !== null, 'zero contra is rejected');
check(validate_contra('Withdraw', 10, '2026-02-31') !== null, 'invalid contra date is rejected');
check(validate_contra('Deposit', 500, '2026-05-01') === null, 'a deposit contra is accepted');

$cashGift = donation_movement([
    'id' => 1,
    'donation_date' => '2026-05-02',
    'amount' => 1000,
    'payment_mode' => 'Cash',
    'purpose' => 'Annadaan',
    'receipt_number' => 'RCPT-2026-0001',
    'donor_name' => 'Meera',
    'entered_by_name' => 'Ramesh',
]);
check($cashGift !== null && $cashGift['receipt_cash'] === 1000.0 && $cashGift['receipt_bank'] === 0.0, 'cash donation is a cash receipt');
check($cashGift !== null && str_contains((string) $cashGift['particulars'], 'Meera') && str_contains((string) $cashGift['particulars'], 'RCPT-2026-0001'), 'donation line names the donor and receipt');

$upiGift = donation_movement([
    'id' => 2,
    'donation_date' => '2026-05-03',
    'amount' => 250.5,
    'payment_mode' => 'UPI',
    'purpose' => '',
    'donor_name' => 'Arun',
    'entered_by_name' => 'Staff',
]);
check($upiGift !== null && $upiGift['receipt_bank'] === 250.5, 'UPI donation is a bank receipt');
check(donation_movement([
    'id' => 3,
    'donation_date' => '2026-05-03',
    'amount' => null,
    'payment_mode' => 'In-Kind',
    'donor_name' => 'Lata',
]) === null, 'in-kind donation is left out of the book');

$cashExpense = expense_movement([
    'id' => 4,
    'expense_date' => '2026-05-04',
    'amount' => 400,
    'payment_mode' => 'Cash',
    'category' => 'Food Supplies',
    'paid_to' => 'Market',
    'entered_by_name' => 'Staff',
]);
$bankExpense = expense_movement([
    'id' => 5,
    'expense_date' => '2026-05-05',
    'amount' => 80,
    'payment_mode' => 'Bank Transfer',
    'category' => 'Utilities',
    'paid_to' => 'Electricity',
    'entered_by_name' => 'Admin',
]);
check($cashExpense !== null && $cashExpense['payment_cash'] === 400.0, 'cash expense is a cash payment');
check($bankExpense !== null && $bankExpense['payment_bank'] === 80.0, 'bank expense is a bank payment');

$deposit = contra_movement([
    'id' => 6,
    'entry_date' => '2026-05-06',
    'direction' => 'Deposit',
    'amount' => 300,
    'note' => 'Weekly deposit',
    'entered_by_name' => 'Ramesh',
]);
$withdraw = contra_movement([
    'id' => 7,
    'entry_date' => '2026-05-07',
    'direction' => 'Withdraw',
    'amount' => 50,
    'note' => '',
    'entered_by_name' => 'Ramesh',
]);
check(
    $deposit !== null && $deposit['payment_cash'] === 300.0 && $deposit['receipt_bank'] === 300.0,
    'a deposit reduces cash and increases bank'
);
check(
    $withdraw !== null && $withdraw['payment_bank'] === 50.0 && $withdraw['receipt_cash'] === 50.0,
    'a withdrawal reduces bank and increases cash'
);

$earlier = donation_movement([
    'id' => 8,
    'donation_date' => '2026-04-10',
    'amount' => 200,
    'payment_mode' => 'Cash',
    'donor_name' => 'Old',
    'entered_by_name' => 'Ramesh',
]);
$book = build_cash_book(
    array_filter([$earlier, $cashGift, $upiGift, $cashExpense, $bankExpense, $deposit, $withdraw]),
    '2026-05-01',
    '2026-05-31',
    1000,
    5000
);
check($book['opening_cash'] === 1200.0, 'April cash movement is brought into the May opening');
check($book['opening_bank'] === 5000.0, 'bank opening stays the year opening when April had no bank movement');
check(count($book['lines']) === 6, 'May lines exclude the April donation');
check($book['lines'][0]['particulars'] !== '' && $book['lines'][0]['date'] <= $book['lines'][5]['date'], 'lines are in date order');
check($book['closing_cash'] === 1550.0, 'closing cash is opening plus receipts minus payments');
check($book['closing_bank'] === 5420.5, 'closing bank includes UPI, the deposit, the withdrawal, and the bank expense');
check(
    abs(($book['opening_cash'] + $book['opening_bank'] + movement_amount($book, 'receipt_cash') + movement_amount($book, 'receipt_bank'))
        - ($book['closing_cash'] + $book['closing_bank'] + movement_amount($book, 'payment_cash') + movement_amount($book, 'payment_bank'))) < 0.001,
    'receipts and payments reconcile to the closing balances'
);

$day = build_day_book($book['lines']);
check(count($day) === 6, 'day book has one row per cash-book line');
check($day[0]['entry'] === 'Receipt · Cash' && $day[0]['entered_by'] === 'Ramesh', 'day book shows who entered a cash receipt');
check($day[4]['entry'] === 'Contra' && $day[4]['amount'] === 300.0, 'day book shows a contra as one amount');

$threw = false;
try {
    build_cash_book([], '2026-03-01', '2026-04-10', 0, 0);
} catch (InvalidArgumentException) {
    $threw = true;
}
check($threw, 'building a book across two financial years fails');

$annadaan = donation_ledger_line([
    'id' => 11,
    'donation_date' => '2026-05-02',
    'amount' => 1000,
    'payment_mode' => 'Cash',
    'purpose' => 'Annadaan',
    'receipt_number' => 'RCPT-2026-0011',
    'donor_name' => 'Meera',
    'entered_by_name' => 'Ramesh',
]);
$blankPurpose = donation_ledger_line([
    'id' => 12,
    'donation_date' => '2026-05-03',
    'amount' => 100,
    'payment_mode' => 'UPI',
    'purpose' => '  ',
    'donor_name' => 'Arun',
    'entered_by_name' => 'Staff',
]);
$inKindLedger = donation_ledger_line([
    'id' => 13,
    'donation_date' => '2026-05-03',
    'amount' => null,
    'payment_mode' => 'In-Kind',
    'purpose' => 'Annadaan',
    'donor_name' => 'Lata',
]);
$festivalGift = donation_ledger_line([
    'id' => 14,
    'donation_date' => '2026-04-20',
    'amount' => 400,
    'payment_mode' => 'Cash',
    'purpose' => 'Festival',
    'donor_name' => 'Nila',
    'entered_by_name' => 'Ramesh',
]);
$festivalSpend = expense_ledger_line([
    'id' => 15,
    'expense_date' => '2026-05-08',
    'amount' => 150,
    'payment_mode' => 'Cash',
    'category' => 'Festival',
    'paid_to' => 'Flowers',
    'entered_by_name' => 'Staff',
]);
$salary = expense_ledger_line([
    'id' => 16,
    'expense_date' => '2026-05-09',
    'amount' => 2000,
    'payment_mode' => 'Bank Transfer',
    'category' => 'Salaries',
    'paid_to' => 'Priest',
    'entered_by_name' => 'Admin',
]);
check($annadaan !== null && $annadaan['head'] === 'Annadaan' && $annadaan['received'] === 1000.0, 'a donation is received under its purpose');
check($blankPurpose !== null && $blankPurpose['head'] === 'General', 'a donation with no purpose is General');
check($inKindLedger === null, 'an in-kind gift does not enter a fund ledger');
check($festivalSpend !== null && $festivalSpend['spent'] === 150.0 && $festivalSpend['head'] === 'Festival', 'an expense is spent under its category');

$ledgers = build_ledgers(
    array_filter([$annadaan, $blankPurpose, $festivalGift, $festivalSpend, $salary]),
    '2026-05-01',
    '2026-05-31'
);
$byName = [];
foreach ($ledgers as $head) {
    $byName[$head['head']] = $head;
}
check(array_keys($byName) === ['Annadaan', 'Festival', 'General', 'Salaries'], 'heads are listed in name order');
check($byName['Annadaan']['received'] === 1000.0 && $byName['Annadaan']['spent'] === 0.0 && $byName['Annadaan']['balance'] === 1000.0, 'Annadaan balance is what was received');
check($byName['Festival']['opening'] === 400.0, 'an April gift is the opening of that fund in May');
check($byName['Festival']['received'] === 0.0 && $byName['Festival']['spent'] === 150.0 && $byName['Festival']['balance'] === 250.0, 'Festival balance is opening plus receipts minus spending');
check($byName['Festival']['lines'][0]['balance'] === 250.0, 'the fund line shows a running balance');
check($byName['Salaries']['spent'] === 2000.0 && $byName['Salaries']['balance'] === -2000.0, 'a head with only expenses has a negative balance');
check($byName['General']['received'] === 100.0, 'General keeps the donation that had no purpose');

$ledgerThrew = false;
try {
    build_ledgers([], '2026-03-01', '2026-04-10');
} catch (InvalidArgumentException) {
    $ledgerThrew = true;
}
check($ledgerThrew, 'a fund ledger cannot cross two financial years');
check(default_ledger_range('2026-09-26') === ['from' => '2026-04-01', 'to' => '2026-09-26'], 'the ledger opens on the financial year to date');

$pdo = db();
$pdo->beginTransaction();
try {
    $contraId = db_exec(
        'INSERT INTO contra_entries (entry_date, direction, amount, note, entered_by) VALUES (?,?,?,?,?)',
        ['2026-09-01', 'Deposit', 15.5, 'test contra', 1]
    );
    record_approval('contra', $contraId, 'Approved', 15.5, 1);
    $loaded = load_book_movements('2026-09-01', '2026-09-01');
    $found = false;
    foreach ($loaded as $row) {
        if ($row['kind'] === 'contra' && abs((float) $row['payment_cash'] - 15.5) < 0.001) {
            $found = true;
        }
    }
    check($found, 'a saved contra is loaded into the book');
    $opening = load_opening_balance('2099-2100');
    check($opening['cash'] === 0.0 && $opening['bank'] === 0.0, 'a year with no opening balance starts at zero');
    $savedHeads = build_ledgers(load_ledger_lines('2026-04-01', '2026-09-26'), '2026-04-01', '2026-09-26');
    check($savedHeads !== [], 'donations and expenses already in the books appear under a head');
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all book tests passed\n";
