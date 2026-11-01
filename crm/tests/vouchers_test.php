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

$upi = normalize_payment_instrument('UPI', 'ab12 cd89', '', '', false);
check($upi['error'] === null && $upi['upi_reference'] === 'AB12CD89' && $upi['cheque_number'] === null, 'a UPI id is stored without spaces');
check(normalize_payment_instrument('UPI', '12', '', '', false)['error'] !== null, 'a short UPI id is rejected');

$cheque = normalize_payment_instrument('Cheque', '', 'ch 4491', '2026-05-02', true);
check(
    $cheque['error'] === null && $cheque['cheque_number'] === 'CH4491' && $cheque['cheque_date'] === '2026-05-02' && $cheque['cheque_cleared'] === 1,
    'a cheque keeps its number, date, and cleared flag'
);
check(normalize_payment_instrument('Cheque', '', '99', '2026-05-02', false)['error'] !== null, 'a cheque number must be at least 4 characters');
check(normalize_payment_instrument('Cheque', '', 'CH4491', '2026-02-31', false)['error'] !== null, 'an impossible cheque date is rejected');

$cash = normalize_payment_instrument('Cash', 'AB12CD89', 'CH4491', '2026-05-02', true);
check($cash['error'] === null && $cash['upi_reference'] === null && $cash['cheque_number'] === null, 'cash ignores a UPI id and a cheque number');

$movement = expense_movement([
    'id' => 1,
    'expense_date' => '2026-05-04',
    'amount' => 400,
    'payment_mode' => 'Cheque',
    'category' => 'Maintenance',
    'paid_to' => 'Plumber',
    'entered_by_name' => 'Staff',
    'voucher_number' => 'VCH-2026-0004',
    'cheque_number' => 'CH4491',
    'cheque_cleared' => 0,
]);
check(
    $movement !== null && str_contains((string) $movement['particulars'], 'VCH-2026-0004') && str_contains((string) $movement['particulars'], 'Chq CH4491'),
    'the cash book names the voucher and the cheque'
);

check(bill_extension('bill.PDF') === 'pdf', 'a PDF bill is accepted');
check(bill_extension('photo.JPG') === 'jpg', 'a JPEG bill is accepted');
check(bill_extension('notes.txt') === null, 'a text file is not a bill');

check(reference_is_mentioned('UPI/AB12CD89/MEERA', 'ab12cd89'), 'the narration can carry the UPI id');
check(!reference_is_mentioned('cheque 12', '12'), 'a reference shorter than 6 characters is ignored');

$farCheque = ['id' => 8, 'entry_date' => '2026-05-01', 'upi_reference' => '', 'cheque_number' => 'CH449100'];
$nearOther = ['id' => 9, 'entry_date' => '2026-05-20', 'upi_reference' => '', 'cheque_number' => ''];
$picked = choose_reconcile_match([$nearOther, $farCheque], 'CLG CH449100 SAHAKARI', '2026-05-21');
check($picked === 8, 'a cheque number in the narration wins over a closer amount');

$onlyNear = choose_reconcile_match(
    [
        ['id' => 3, 'entry_date' => '2026-05-01', 'upi_reference' => 'ZZZZZZ999', 'cheque_number' => ''],
        ['id' => 4, 'entry_date' => '2026-05-20', 'upi_reference' => '', 'cheque_number' => ''],
    ],
    'NEFT FROM DONOR',
    '2026-05-21'
);
check($onlyNear === 4, 'without a reference, the match stays inside 3 days');

$closer = choose_reconcile_match(
    [
        ['id' => 1, 'entry_date' => '2026-04-01', 'upi_reference' => 'SAMEUPI01', 'cheque_number' => ''],
        ['id' => 2, 'entry_date' => '2026-05-18', 'upi_reference' => 'SAMEUPI01', 'cheque_number' => ''],
    ],
    'UPI SAMEUPI01',
    '2026-05-21'
);
check($closer === 2, 'two narration matches keep the date closer to the bank line');
check(choose_reconcile_match([], 'UPI SAMEUPI01', '2026-05-21') === null, 'no candidate means no match');

$pdo = db();
$pdo->beginTransaction();
try {
    $first = next_voucher_number($pdo, '2026-09-26');
    $march = next_voucher_number($pdo, '2027-03-15');
    check(str_starts_with($first, 'VCH-2026-') && $first === $march, 'April to March share one voucher series');
    check(str_starts_with(next_voucher_number($pdo, '2026-03-15'), 'VCH-2025-'), 'March belongs to the previous voucher series');
    db_exec(
        'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, voucher_number, upi_reference, added_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        ['Maintenance', 'test voucher', 10, 'Tester', '2026-09-26', 'UPI', $first, 'TESTUPI01', 1]
    );
    $second = next_voucher_number($pdo, '2026-09-26');
    check($second !== $first && (int) substr($second, -4) === (int) substr($first, -4) + 1, 'the next voucher number increases by one');
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all voucher tests passed\n";
