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
            'modules' => 'Deity vastra. Approval status, subscriber status, and invoice status stay fixed because the books depend on those words.',
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
        'plans' => [
            'label' => 'Subscription plan',
            'modules' => 'Subscriptions, the plan box.',
            'kind' => 'lines',
            'required' => [],
        ],
        'billing_cycles' => [
            'label' => 'Billing cycle',
            'modules' => 'Subscriptions. Monthly is the cycle counted in monthly recurring revenue.',
            'kind' => 'lines',
            'required' => ['Monthly', 'Quarterly', 'Yearly'],
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
        'plans' => ['Monthly Annadaan Seva', 'Monthly Mahaprasad Seva', 'Monthly Deepa Seva', 'Quarterly Vastra Seva', 'Yearly Nitya Seva'],
        'billing_cycles' => ['Monthly', 'Quarterly', 'Yearly'],
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
        }
    }
    return implode("\n", $lines);
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
    $seen = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if ($meta['kind'] === 'lines') {
            $error = selection_name_error($line, $key === 'plans' || $key === 'expense_categories' ? 80 : 50);
            if ($error !== null) {
                return $error;
            }
            if (isset($seen[$line])) {
                return $line . ' is listed twice.';
            }
            $seen[$line] = true;
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
            if (isset($seen[$name])) {
                return $name . ' is listed twice.';
            }
            $seen[$name] = true;
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
            if (isset($seen[$name])) {
                return $name . ' is listed twice.';
            }
            $seen[$name] = true;
            $items[] = ['name' => $name, 'direction' => $direction, 'approval' => $approval, 'store' => $store];
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
            if (isset($seen[$value])) {
                return $value . ' is listed twice.';
            }
            $seen[$value] = true;
            $items[] = ['value' => $value, 'label' => $label];
        }
    }
    if ($items === []) {
        return 'Enter at least one choice.';
    }
    foreach ($meta['required'] as $required) {
        if (!isset($seen[$required])) {
            return 'Keep ' . $required . '. The forms rely on that name.';
        }
    }
    if ($key === 'payment_modes') {
        $books = [];
        foreach ($items as $item) {
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

/** @param array<string, mixed> $posted */
function save_selections(array $posted): ?string
{
    $parsed = parse_selections($posted);
    if ($parsed['error'] !== null) {
        return $parsed['error'];
    }
    $json = json_encode($parsed['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
