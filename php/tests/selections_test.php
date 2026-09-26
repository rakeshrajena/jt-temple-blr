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

/** @return array<string, string> */
function selection_form(): array
{
    $posted = [];
    foreach (array_keys(selection_catalog()) as $key) {
        $posted[$key] = selection_text($key);
    }
    return $posted;
}

$posted = selection_form();
check(parse_selections($posted)['error'] === null, 'the current lists can be saved again');

$withoutCash = $posted;
$withoutCash['payment_modes'] = str_replace("Cash | cash\n", '', $withoutCash['payment_modes']);
check(parse_selections($withoutCash)['error'] !== null, 'Cash cannot be removed');

$empty = $posted;
$empty['expense_categories'] = '';
check(parse_selections($empty)['error'] !== null, 'an empty expense list is rejected');

$path = selection_path();
$backup = is_file($path) ? (string) file_get_contents($path) : null;
try {
    $added = $posted;
    $added['expense_categories'] .= "\nTemple Flowers";
    $added['payment_modes'] .= "\nNEFT | bank";
    check(save_selections($added) === null, 'a new expense category and bank mode can be saved');
    check(in_array('Temple Flowers', selection_values('expense_categories'), true), 'the new expense category is in the list');
    check(book_account('NEFT') === 'bank', 'NEFT enters the bank book');
    check(book_account('Cash') === 'cash', 'Cash stays in the cash book');
    check(book_account('UPI') === 'bank', 'UPI stays in the bank book');
    check(book_account('In-Kind') === null, 'In-Kind stays out of the cash book');
    $clause = money_mode_clause('d.payment_mode');
    check(str_contains($clause, "'NEFT'") && str_contains($clause, "'UPI'"), 'received totals include the bank modes');
    check(!str_contains($clause, 'In-Kind'), 'received totals leave out In-Kind');
    check(in_array('Card', money_payment_modes(), true) && in_array('Netbanking', money_payment_modes(), true), 'Card and Netbanking are shared money modes');
    check(!in_array('In-Kind', money_payment_modes(), true), 'In-Kind is not offered on a money form');
    check(!in_array('Added', inventory_form_movements(), true), 'Added is stored when stock comes in and is not a manual movement');
    check(stock_direction('Returned') === 1 && stock_direction('Issued') === -1, 'movement direction follows the list');
    check(stock_needs_approval('Damaged', STOCK_WRITE_OFF_LIMIT + 1) && !stock_needs_approval('Issued', 100), 'only marked movements wait for approval');
    check(apply_selection_change('expense_categories', 'add', 0, ['name' => 'temple flowers']) !== null, 'a different capitalisation is still a duplicate');
    check(apply_selection_change('expense_categories', 'add', 0, ['name' => 'Temple Flowers']) !== null, 'the same expense category cannot be added twice');
    $cashIndex = 0;
    foreach (selection_editor_rows('payment_modes') as $index => $row) {
        if ($row['name'] === 'Cash') {
            $cashIndex = $index;
        }
    }
    check(apply_selection_change('payment_modes', 'delete', $cashIndex, []) !== null, 'Cash cannot be deleted from the table');
    check(book_account('Cash') === 'cash', 'Cash remains after a refused delete');
} finally {
    if ($backup === null) {
        if (is_file($path)) {
            unlink($path);
        }
    } else {
        file_put_contents($path, $backup);
    }
    load_selections(true);
}

check(!in_array('Temple Flowers', selection_values('expense_categories'), true), 'the test category is removed');
check(book_account('NEFT') === null, 'the test bank mode is removed');

exit($failed > 0 ? 1 : 0);
