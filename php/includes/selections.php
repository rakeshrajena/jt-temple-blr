<?php
declare(strict_types=1);

function selection_path(): string
{
    return APP_ROOT . '/storage/selections.json';
}

/** @return array<string, array{label: string, modules: string, kind: string, required: list<string>}> */
function selection_catalog(): array
{
    return [
        'inventory_categories' => [
            'label' => 'Inventory category',
            'modules' => 'Inventory on-hand, inventory purchase, and an inventory gift on Donations.',
            'kind' => 'lines',
            'required' => ['Other'],
        ],
        'expense_categories' => [
            'label' => 'Expense category',
            'modules' => 'Expenses. A stock purchase records its payment under Other.',
            'kind' => 'lines',
            'required' => ['Other'],
        ],
        'units' => [
            'label' => 'Unit',
            'modules' => 'Inventory, Food stock, and an inventory gift on Donations.',
            'kind' => 'lines',
            'required' => ['pcs', 'kg'],
        ],
        'conditions' => [
            'label' => 'Condition',
            'modules' => 'Inventory, when stock is added and when condition or location is updated.',
            'kind' => 'lines',
            'required' => ['New', 'Good', 'Fair', 'Needs Repair', 'Damaged', 'Retired'],
        ],
        'sources' => [
            'label' => 'Source',
            'modules' => 'Inventory and Deity vastra.',
            'kind' => 'lines',
            'required' => ['Purchased', 'Donated'],
        ],
        'payment_modes' => [
            'label' => 'Payment mode',
            'modules' => 'One list for every form. Cash and bank modes are on Expenses, Inventory purchase, and Receive pledge. A mode marked none, such as In-Kind, is on Donations only because it does not enter the cash book.',
            'kind' => 'payment',
            'required' => ['Cash', 'Bank Transfer', 'UPI', 'Cheque', 'Card', 'Netbanking', 'In-Kind'],
        ],
        'movements' => [
            'label' => 'Movement',
            'modules' => 'Inventory movement. Added is stored when stock comes in and is not a manual choice. Used is the Food stock usage button. Issued, Returned, Damaged, Lost, and Retired are the Inventory movement list.',
            'kind' => 'movement',
            'required' => ['Added', 'Issued', 'Returned', 'Damaged', 'Lost', 'Retired', 'Used'],
        ],
        'vastra_statuses' => [
            'label' => 'Vastra status',
            'modules' => 'Deity vastra. Approval status and invoice status stay fixed because the books depend on those words.',
            'kind' => 'lines',
            'required' => ['In Store', 'In Use', 'Retired'],
        ],
        'donation_types' => [
            'label' => 'Donation type',
            'modules' => 'Donations. Cash and Other ask for an amount. Food, Vastra, and Inventory open the matching extra fields.',
            'kind' => 'labeled',
            'required' => ['Cash', 'Food', 'Vastra', 'Inventory', 'Other'],
        ],
        'purposes' => [
            'label' => 'Donation purpose',
            'modules' => 'Donations. A pledge purpose is typed on the devotee page and is not taken from this list.',
            'kind' => 'lines',
            'required' => ['General'],
        ],
        'puja_purposes' => [
            'label' => 'Puja purpose',
            'modules' => 'Coupons. The coupon name and the purpose offer these names. Choosing a coupon name fills the amount. Choosing a purpose does not. Quantity 1 does not wait for approval.',
            'kind' => 'priced',
            'required' => [],
        ],
        'plans' => [
            'label' => 'Subscription plan',
            'modules' => 'Subscriptions. The plan box suggests these names while typing.',
            'kind' => 'lines',
            'required' => [],
        ],
        'billing_cycles' => [
            'label' => 'Billing cycle',
            'modules' => 'Subscriptions. The billing cycle box suggests these while typing. Monthly is the cycle counted in monthly recurring revenue.',
            'kind' => 'lines',
            'required' => ['Monthly', 'Quarterly', 'Yearly'],
        ],
        'subscriber_statuses' => [
            'label' => 'Subscriber status',
            'modules' => 'Subscriptions. The status box suggests these while typing. Active is counted in monthly recurring revenue and can receive a new invoice.',
            'kind' => 'lines',
            'required' => ['Active'],
        ],
        'deities' => [
            'label' => 'Deity',
            'modules' => 'Deity vastra and a vastra gift on Donations.',
            'kind' => 'lines',
            'required' => ['Jagannath', 'Balabhadra', 'Subhadra', 'Sudarshan'],
        ],
    ];
}

/** @return array<string, mixed> */
function selection_defaults(): array
{
    return [
        'inventory_categories' => ['Hardware', 'Electronics', 'Furniture', 'Puja Items', 'Kitchen Equipment', 'Decoration', 'Other'],
        'expense_categories' => ['Food Supplies', 'Maintenance', 'Utilities', 'Salaries', 'Festival', 'Decoration', 'Other'],
        'units' => ['pcs', 'kg', 'gram', 'litre', 'packet', 'metre'],
        'conditions' => ['New', 'Good', 'Fair', 'Needs Repair', 'Damaged', 'Retired'],
        'sources' => ['Purchased', 'Donated'],
        'payment_modes' => [
            ['name' => 'Cash', 'book' => 'cash'],
            ['name' => 'Bank Transfer', 'book' => 'bank'],
            ['name' => 'UPI', 'book' => 'bank'],
            ['name' => 'Cheque', 'book' => 'bank'],
            ['name' => 'Card', 'book' => 'bank'],
            ['name' => 'Netbanking', 'book' => 'bank'],
            ['name' => 'In-Kind', 'book' => 'none'],
        ],
        'movements' => [
            ['name' => 'Added', 'direction' => 'in', 'approval' => false, 'store' => 'both'],
            ['name' => 'Issued', 'direction' => 'out', 'approval' => false, 'store' => 'inventory'],
            ['name' => 'Returned', 'direction' => 'in', 'approval' => false, 'store' => 'inventory'],
            ['name' => 'Damaged', 'direction' => 'out', 'approval' => true, 'store' => 'inventory'],
            ['name' => 'Lost', 'direction' => 'out', 'approval' => true, 'store' => 'inventory'],
            ['name' => 'Retired', 'direction' => 'out', 'approval' => true, 'store' => 'inventory'],
            ['name' => 'Used', 'direction' => 'out', 'approval' => true, 'store' => 'food'],
        ],
        'vastra_statuses' => ['In Store', 'In Use', 'Retired'],
        'donation_types' => [
            ['value' => 'Cash', 'label' => 'Cash'],
            ['value' => 'Food', 'label' => 'Food (in-kind)'],
            ['value' => 'Vastra', 'label' => 'Vastra / Cloths (in-kind)'],
            ['value' => 'Inventory', 'label' => 'Inventory Item (in-kind)'],
            ['value' => 'Other', 'label' => 'Other'],
        ],
        'purposes' => ['General', 'Annadaan', 'Ratha Yatra', 'Construction', 'Vastra Seva'],
        'puja_purposes' => [
            ['name' => 'Daily / Regular Puja', 'amount' => 51],
            ['name' => 'Satyanarayan Puja', 'amount' => 501],
            ['name' => 'House Warming / Griha Pravesh', 'amount' => 1101],
            ['name' => 'Vehicle Puja', 'amount' => 251],
            ['name' => 'Annaprashana', 'amount' => 501],
            ['name' => 'Naming Ceremony', 'amount' => 501],
            ['name' => 'Marriage / Vivaha', 'amount' => 2101],
            ['name' => 'Upanayana / Thread Ceremony', 'amount' => 1101],
            ['name' => 'Mundan Ceremony', 'amount' => 351],
            ['name' => 'Shraddha / Pitru Puja', 'amount' => 501],
            ['name' => 'Birthday / Ayushya Puja', 'amount' => 251],
            ['name' => 'Ganesh Puja', 'amount' => 251],
            ['name' => 'Shiva Puja / Rudrabhishek', 'amount' => 501],
            ['name' => 'Lakshmi Puja', 'amount' => 251],
            ['name' => 'Durga Puja', 'amount' => 501],
            ['name' => 'Hanuman Puja', 'amount' => 151],
            ['name' => 'Navagraha Puja', 'amount' => 1101],
            ['name' => 'Health & Well-being', 'amount' => 251],
            ['name' => 'Career / Success', 'amount' => 251],
            ['name' => 'Business / Prosperity', 'amount' => 501],
            ['name' => 'Family Welfare', 'amount' => 251],
            ['name' => 'Festival / Special Puja', 'amount' => 501],
            ['name' => 'Manasika Jagna', 'amount' => 1101],
            ['name' => 'Full Day Ritual Puja', 'amount' => 2101],
            ['name' => 'Other', 'amount' => 101],
        ],
        'plans' => ['Mahaprasad', 'Flower and Bhog', 'Deepa Seva', 'Annadan Seva', 'Monthly Annadaan Seva', 'Monthly Mahaprasad Seva', 'Monthly Deepa Seva', 'Quarterly Vastra Seva', 'Yearly Nitya Seva'],
        'billing_cycles' => ['Monthly', 'Quarterly', 'Yearly'],
        'subscriber_statuses' => ['Active', 'Paused', 'Inactive', 'Cancelled'],
        'deities' => ['Jagannath', 'Balabhadra', 'Subhadra', 'Sudarshan'],
    ];
}

/** @return array<string, mixed> */
function load_selections(bool $reload = false): array
{
    static $cache = null;
    if ($reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    $cache = selection_defaults();
    if (!is_file(selection_path())) {
        return $cache;
    }
    $decoded = json_decode((string) file_get_contents(selection_path()), true);
    if (!is_array($decoded)) {
        return $cache;
    }
    foreach ($cache as $key => $default) {
        if (isset($decoded[$key]) && is_array($decoded[$key]) && $decoded[$key] !== []) {
            $cache[$key] = $decoded[$key];
        }
    }
    return $cache;
}

function selection_choice(string $key, string $value): ?string
{
    $needle = mb_strtolower(trim($value));
    if ($needle === '') {
        return null;
    }
    foreach (selection_values($key) as $option) {
        if (mb_strtolower($option) === $needle) {
            return $option;
        }
    }
    return null;
}

/** @return list<string> */
function selection_values(string $key): array
{
    $rows = load_selections()[$key] ?? [];
    $values = [];
    foreach ($rows as $row) {
        if (is_string($row) && $row !== '') {
            $values[] = $row;
        }
    }
    return $values !== [] ? $values : (selection_defaults()[$key] ?? []);
}

/** @return list<array{value: string, label: string}> */
function selection_pairs(string $key): array
{
    $pairs = [];
    foreach (load_selections()[$key] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $value = trim((string) ($row['value'] ?? ''));
        $label = trim((string) ($row['label'] ?? $value));
        if ($value !== '') {
            $pairs[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
        }
    }
    return $pairs;
}

/** @return list<string> */
function payment_mode_names(): array
{
    $names = [];
    foreach (load_selections()['payment_modes'] ?? [] as $row) {
        if (is_array($row) && trim((string) ($row['name'] ?? '')) !== '') {
            $names[] = (string) $row['name'];
        }
    }
    return $names;
}

/** @return list<string> */
function money_payment_modes(): array
{
    $names = [];
    foreach (load_selections()['payment_modes'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $book = (string) ($row['book'] ?? '');
        if ($book === 'cash' || $book === 'bank') {
            $names[] = (string) $row['name'];
        }
    }
    return $names;
}

function book_account(string $paymentMode): ?string
{
    foreach (load_selections()['payment_modes'] ?? [] as $row) {
        if (is_array($row) && (string) ($row['name'] ?? '') === $paymentMode) {
            $book = (string) ($row['book'] ?? '');
            return $book === 'cash' || $book === 'bank' ? $book : null;
        }
    }
    return null;
}

function money_mode_clause(string $column): string
{
    if (preg_match('/^[A-Za-z0-9_.]+$/', $column) !== 1) {
        return '1=0';
    }
    $quoted = [];
    foreach (money_payment_modes() as $mode) {
        $quoted[] = "'" . str_replace("'", "''", $mode) . "'";
    }
    if ($quoted === []) {
        return '1=0';
    }
    return $column . ' IN (' . implode(',', $quoted) . ')';
}

/** @return list<array{name: string, direction: string, approval: bool, store: string}> */
function movement_records(): array
{
    $rows = [];
    foreach (load_selections()['movements'] ?? [] as $row) {
        if (!is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
            continue;
        }
        $rows[] = [
            'name' => (string) $row['name'],
            'direction' => (string) ($row['direction'] ?? 'out'),
            'approval' => (bool) ($row['approval'] ?? false),
            'store' => (string) ($row['store'] ?? 'inventory'),
        ];
    }
    return $rows;
}

function movement_record(string $name): ?array
{
    foreach (movement_records() as $row) {
        if ($row['name'] === $name) {
            return $row;
        }
    }
    return null;
}

/** @return list<string> */
function movements_for(string $store): array
{
    $names = [];
    foreach (movement_records() as $row) {
        if ($row['store'] === $store || $row['store'] === 'both') {
            $names[] = $row['name'];
        }
    }
    return $names;
}

/** @return list<string> */
function inventory_form_movements(): array
{
    $names = [];
    foreach (movement_records() as $row) {
        if ($row['store'] === 'inventory') {
            $names[] = $row['name'];
        }
    }
    return $names;
}

function selection_text(string $key): string
{
    $kind = selection_catalog()[$key]['kind'] ?? 'lines';
    $rows = load_selections()[$key] ?? [];
    $lines = [];
    foreach ($rows as $row) {
        if ($kind === 'lines' && is_string($row)) {
            $lines[] = $row;
        } elseif ($kind === 'payment' && is_array($row)) {
            $lines[] = (string) $row['name'] . ' | ' . (string) $row['book'];
        } elseif ($kind === 'movement' && is_array($row)) {
            $line = (string) $row['name'] . ' | ' . (string) $row['direction'] . ' | ' . (string) $row['store'];
            if (!empty($row['approval'])) {
                $line .= ' | approval';
            }
            $lines[] = $line;
        } elseif ($kind === 'labeled' && is_array($row)) {
            $value = (string) $row['value'];
            $label = (string) ($row['label'] ?? $value);
            $lines[] = $label === $value ? $value : $value . ' | ' . $label;
        } elseif ($kind === 'priced' && is_array($row)) {
            $lines[] = (string) $row['name'] . ' | ' . number_format((float) $row['amount'], 2, '.', '');
        }
    }
    return implode("\n", $lines);
}

function selection_name_limit(string $key): int
{
    return match ($key) {
        'plans', 'expense_categories', 'puja_purposes' => 80,
        'billing_cycles' => 20,
        'subscriber_statuses', 'payment_modes', 'movements', 'donation_types' => 30,
        default => 50,
    };
}

function selection_amount_error(string $raw): ?string
{
    if ($raw === '' || !is_numeric($raw)) {
        return 'Enter an amount above zero.';
    }
    $amount = round((float) $raw, 2);
    if ($amount < 0.01 || $amount > 9999999.99) {
        return 'Enter an amount from 0.01 to 99,99,999.99.';
    }
    return null;
}

function selection_name_error(string $name, int $max): ?string
{
    if ($name === '' || mb_strlen($name) > $max) {
        return 'Each choice needs a name of 1 to ' . $max . ' characters.';
    }
    if (preg_match('/[|\r\n\'"<>]/', $name) === 1) {
        return 'A choice cannot contain |, quotes, or < >.';
    }
    return null;
}

function selection_identity(string $kind, mixed $item): string
{
    if ($kind === 'lines' && is_string($item)) {
        return mb_strtolower($item);
    }
    if (!is_array($item)) {
        return '';
    }
    $name = $kind === 'labeled' ? (string) ($item['value'] ?? '') : (string) ($item['name'] ?? '');
    return mb_strtolower($name);
}

/** @return list<array<string, mixed>> */
function selection_editor_rows(string $key): array
{
    $kind = selection_catalog()[$key]['kind'] ?? 'lines';
    $rows = [];
    foreach (load_selections()[$key] ?? [] as $row) {
        if ($kind === 'lines' && is_string($row) && $row !== '') {
            $rows[] = ['name' => $row];
        } elseif ($kind === 'payment' && is_array($row)) {
            $rows[] = ['name' => (string) ($row['name'] ?? ''), 'book' => (string) ($row['book'] ?? '')];
        } elseif ($kind === 'movement' && is_array($row)) {
            $rows[] = [
                'name' => (string) ($row['name'] ?? ''),
                'direction' => (string) ($row['direction'] ?? 'out'),
                'store' => (string) ($row['store'] ?? 'inventory'),
                'approval' => !empty($row['approval']),
            ];
        } elseif ($kind === 'labeled' && is_array($row)) {
            $value = (string) ($row['value'] ?? '');
            $rows[] = ['value' => $value, 'label' => (string) ($row['label'] ?? $value)];
        } elseif ($kind === 'priced' && is_array($row)) {
            $rows[] = [
                'name' => (string) ($row['name'] ?? ''),
                'amount' => number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
            ];
        }
    }
    return $rows;
}

/**
 * @param array<string, mixed> $posted
 * @return array{error: ?string, data: array<string, mixed>}
 */
function parse_selections(array $posted): array
{
    $data = [];
    foreach (selection_catalog() as $key => $meta) {
        $raw = $posted[$key] ?? '';
        if (!is_string($raw)) {
            return ['error' => 'Enter the ' . $meta['label'] . ' list.', 'data' => []];
        }
        if (mb_strlen($raw) > 4000) {
            return ['error' => $meta['label'] . ' is too long.', 'data' => []];
        }
        $parsed = parse_selection_block($key, $raw);
        if (is_string($parsed)) {
            return ['error' => $meta['label'] . ': ' . $parsed, 'data' => []];
        }
        $data[$key] = $parsed;
    }
    return ['error' => null, 'data' => $data];
}

/** @return list<mixed>|string */
function parse_selection_block(string $key, string $raw): array|string
{
    $meta = selection_catalog()[$key];
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if ($meta['kind'] === 'lines') {
            $error = selection_name_error($line, selection_name_limit($key));
            if ($error !== null) {
                return $error;
            }
            $items[] = $line;
        } elseif ($meta['kind'] === 'payment') {
            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            $book = strtolower($parts[1] ?? '');
            $error = selection_name_error($name, 30);
            if ($error !== null) {
                return $error;
            }
            if (!in_array($book, ['cash', 'bank', 'none'], true)) {
                return $name . ' needs cash, bank, or none after the |.';
            }
            $items[] = ['name' => $name, 'book' => $book];
        } elseif ($meta['kind'] === 'movement') {
            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            $direction = strtolower($parts[1] ?? '');
            $store = strtolower($parts[2] ?? '');
            $approval = strtolower($parts[3] ?? '') === 'approval';
            $error = selection_name_error($name, 30);
            if ($error !== null) {
                return $error;
            }
            if (!in_array($direction, ['in', 'out'], true)) {
                return $name . ' needs in or out after the first |.';
            }
            if (!in_array($store, ['inventory', 'food', 'both'], true)) {
                return $name . ' needs inventory, food, or both after the second |.';
            }
            $items[] = ['name' => $name, 'direction' => $direction, 'approval' => $approval, 'store' => $store];
        } elseif ($meta['kind'] === 'priced') {
            $parts = array_map('trim', explode('|', $line, 2));
            $name = $parts[0] ?? '';
            $amountRaw = str_replace(',', '', $parts[1] ?? '');
            $error = selection_name_error($name, selection_name_limit($key));
            if ($error !== null) {
                return $error;
            }
            $amountError = selection_amount_error($amountRaw);
            if ($amountError !== null) {
                return $name . ': ' . $amountError;
            }
            $items[] = ['name' => $name, 'amount' => round((float) $amountRaw, 2)];
        } else {
            $parts = array_map('trim', explode('|', $line, 2));
            $value = $parts[0] ?? '';
            $label = $parts[1] ?? $value;
            $error = selection_name_error($value, 30);
            if ($error !== null) {
                return $error;
            }
            $labelError = selection_name_error($label, 80);
            if ($labelError !== null) {
                return $labelError;
            }
            $items[] = ['value' => $value, 'label' => $label];
        }
    }
    return validate_selection_list($key, $items);
}

/** @param list<mixed> $items
 *  @return list<mixed>|string
 */
function validate_selection_list(string $key, array $items): array|string
{
    $meta = selection_catalog()[$key];
    if ($items === []) {
        return 'Enter at least one choice.';
    }
    $seen = [];
    foreach ($items as $item) {
        $identity = selection_identity($meta['kind'], $item);
        $label = $meta['kind'] === 'lines' && is_string($item)
            ? $item
            : (string) (is_array($item) ? ($item['value'] ?? $item['name'] ?? '') : '');
        if ($identity === '' || isset($seen[$identity])) {
            return $label . ' is already in this list.';
        }
        $seen[$identity] = true;
    }
    $exact = [];
    foreach ($items as $item) {
        if ($meta['kind'] === 'lines' && is_string($item)) {
            $exact[$item] = true;
        } elseif (is_array($item)) {
            $exact[(string) ($item['value'] ?? $item['name'] ?? '')] = true;
        }
    }
    foreach ($meta['required'] as $required) {
        if (!isset($exact[$required])) {
            return 'Keep ' . $required . '. The forms rely on that name.';
        }
    }
    if ($key === 'payment_modes') {
        $books = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                return 'A payment mode is incomplete.';
            }
            if ($item['name'] === 'Cash' && $item['book'] !== 'cash') {
                return 'Cash must stay a cash mode.';
            }
            if ($item['name'] === 'In-Kind' && $item['book'] !== 'none') {
                return 'In-Kind must stay out of the cash book.';
            }
            $books[$item['book']] = true;
        }
        if (!isset($books['cash'], $books['bank'])) {
            return 'Keep at least one cash mode and one bank mode.';
        }
    }
    return $items;
}

/**
 * @param array<string, string> $input
 * @return array{error: ?string, item: mixed}
 */
function selection_item_from_input(string $key, array $input): array
{
    $kind = selection_catalog()[$key]['kind'] ?? 'lines';
    if ($kind === 'lines') {
        $name = trim($input['name'] ?? '');
        return ['error' => selection_name_error($name, selection_name_limit($key)), 'item' => $name];
    }
    if ($kind === 'payment') {
        $name = trim($input['name'] ?? '');
        $book = strtolower(trim($input['book'] ?? ''));
        $error = selection_name_error($name, 30);
        if ($error === null && !in_array($book, ['cash', 'bank', 'none'], true)) {
            $error = 'Choose cash, bank, or none.';
        }
        return ['error' => $error, 'item' => ['name' => $name, 'book' => $book]];
    }
    if ($kind === 'movement') {
        $name = trim($input['name'] ?? '');
        $direction = strtolower(trim($input['direction'] ?? ''));
        $store = strtolower(trim($input['store'] ?? ''));
        $error = selection_name_error($name, 30);
        if ($error === null && !in_array($direction, ['in', 'out'], true)) {
            $error = 'Choose whether the quantity comes in or goes out.';
        }
        if ($error === null && !in_array($store, ['inventory', 'food', 'both'], true)) {
            $error = 'Choose inventory, food, or both.';
        }
        return ['error' => $error, 'item' => [
            'name' => $name,
            'direction' => $direction,
            'store' => $store,
            'approval' => ($input['approval'] ?? '') === '1',
        ]];
    }
    if ($kind === 'priced') {
        $name = trim($input['name'] ?? '');
        $amountRaw = str_replace(',', '', trim($input['amount'] ?? ''));
        $error = selection_name_error($name, selection_name_limit($key)) ?? selection_amount_error($amountRaw);
        return ['error' => $error, 'item' => ['name' => $name, 'amount' => $error === null ? round((float) $amountRaw, 2) : 0]];
    }
    $value = trim($input['value'] ?? '');
    $label = trim($input['label'] ?? '');
    if ($label === '') {
        $label = $value;
    }
    $error = selection_name_error($value, 30) ?? selection_name_error($label, 80);
    return ['error' => $error, 'item' => ['value' => $value, 'label' => $label]];
}

/** @param array<string, string> $input */
function apply_selection_change(string $key, string $op, int $index, array $input): ?string
{
    $catalog = selection_catalog();
    if (!isset($catalog[$key]) || !in_array($op, ['add', 'update', 'delete'], true)) {
        return 'That choice list was not recognised.';
    }
    $items = array_values(load_selections()[$key] ?? []);
    if (($op === 'update' || $op === 'delete') && !isset($items[$index])) {
        return 'That choice is no longer in the list.';
    }
    if ($op === 'delete') {
        array_splice($items, $index, 1);
    } else {
        $built = selection_item_from_input($key, $input);
        if ($built['error'] !== null) {
            return $catalog[$key]['label'] . ': ' . $built['error'];
        }
        $item = $built['item'];
        if ($op === 'add') {
            $items[] = $item;
        } else {
            $items[$index] = $item;
        }
    }
    $checked = validate_selection_list($key, $items);
    if (is_string($checked)) {
        return $catalog[$key]['label'] . ': ' . $checked;
    }
    $data = load_selections();
    $data[$key] = $checked;
    return write_selection_file($data);
}

/** @param array<string, mixed> $data */
function write_selection_file(array $data): ?string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return 'The choice list could not be saved.';
    }
    $dir = dirname(selection_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return 'The choice list could not be saved.';
    }
    if (file_put_contents(selection_path(), $json . "\n", LOCK_EX) === false) {
        return 'The choice list could not be saved.';
    }
    load_selections(true);
    return null;
}

/** @param array<string, mixed> $posted */
function save_selections(array $posted): ?string
{
    $parsed = parse_selections($posted);
    if ($parsed['error'] !== null) {
        return $parsed['error'];
    }
    return write_selection_file($parsed['data']);
}

/** @return list<array{name: string, amount: string}> */
function puja_purposes(): array
{
    $rows = [];
    foreach (load_selections()['puja_purposes'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $amount = round((float) ($row['amount'] ?? 0), 2);
        if ($name === '' || $amount < 0.01) {
            continue;
        }
        $rows[] = ['name' => $name, 'amount' => number_format($amount, 2, '.', '')];
    }
    return $rows;
}

function puja_purpose_amount(string $name): ?float
{
    $needle = mb_strtolower(trim($name));
    if ($needle === '') {
        return null;
    }
    foreach (puja_purposes() as $row) {
        if (mb_strtolower($row['name']) === $needle) {
            return (float) $row['amount'];
        }
    }
    return null;
}

function write_default_selections(): void
{
    if (is_file(selection_path())) {
        return;
    }
    $json = json_encode(selection_defaults(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    $dir = dirname(selection_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }
    file_put_contents(selection_path(), $json . "\n", LOCK_EX);
}

function ensure_subscriber_schema(PDO $pdo): void
{
    ensure_column($pdo, 'subscribers', 'family_members', 'VARCHAR(300) NULL');
    ensure_column($pdo, 'subscribers', 'gotra', 'VARCHAR(80) NULL');
    ensure_column($pdo, 'subscribers', 'seva_date', 'DATE NULL');
    ensure_subscriber_status_log($pdo);
}

function ensure_selection_columns(PDO $pdo): void
{
    $columns = [
        ['inventory_items', 'item_condition', "VARCHAR(30) NOT NULL DEFAULT 'Good'"],
        ['inventory_items', 'source', "VARCHAR(30) NOT NULL DEFAULT 'Purchased'"],
        ['inventory_movements', 'movement_type', 'VARCHAR(30) NOT NULL'],
        ['purchases', 'payment_mode', 'VARCHAR(30) NOT NULL'],
        ['donations', 'donation_type', 'VARCHAR(30) NOT NULL'],
        ['donations', 'payment_mode', 'VARCHAR(30) NOT NULL'],
        ['expenses', 'payment_mode', 'VARCHAR(30) NOT NULL'],
        ['vastra_items', 'source', "VARCHAR(30) NULL DEFAULT 'Purchased'"],
        ['vastra_items', 'status', "VARCHAR(30) NULL DEFAULT 'In Store'"],
        ['subscribers', 'frequency', "VARCHAR(20) NOT NULL DEFAULT 'Monthly'"],
        ['subscribers', 'status', "VARCHAR(30) NOT NULL DEFAULT 'Active'"],
        ['food_usage_log', 'txn_type', 'VARCHAR(30) NOT NULL'],
        ['stock_requests', 'movement_type', 'VARCHAR(30) NOT NULL'],
    ];
    write_default_selections();
    foreach ($columns as [$table, $column, $definition]) {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
            continue;
        }
        $row = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column))->fetch();
        if ($row === false) {
            continue;
        }
        $current = strtolower((string) $row['Type']);
        $widen = str_starts_with($current, 'enum(');
        if (
            preg_match('/^varchar\((\d+)\)/', $current, $currentWidth) === 1
            && preg_match('/VARCHAR\((\d+)\)/', $definition, $targetWidth) === 1
            && (int) $currentWidth[1] < (int) $targetWidth[1]
        ) {
            $widen = true;
        }
        if ($widen) {
            $pdo->exec('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` ' . $definition);
        }
    }
}
