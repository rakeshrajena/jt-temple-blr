<?php
declare(strict_types=1);

function dispatch_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();

    if ($method === 'POST') {
        require_csrf();
    }

    if ($path === 'login') {
        action_login($method);
        return;
    }
    if ($path === 'logout' && $method === 'GET') {
        $_SESSION = [];
        session_destroy();
        redirect(url('login'));
    }
    if ($path === 'pay' || preg_match('#^pay/([^/]+)$#', $path, $m) === 1) {
        if ($path === 'pay') {
            http_response_code(404);
            render('pay_invalid', [], false);
            return;
        }
        if ($method === 'GET') {
            action_pay($m[1]);
            return;
        }
    }
    if (preg_match('#^pay/([^/]+)/confirm$#', $path, $m) === 1 && $method === 'POST') {
        action_pay_confirm($m[1]);
        return;
    }

    if ($path === '' || $path === 'dashboard') {
        action_dashboard();
        return;
    }
    if ($path === 'inventory') {
        action_inventory($method);
        return;
    }
    if ($path === 'food') {
        action_food($method);
        return;
    }
    if ($path === 'food/coupons') {
        action_food_coupons($method);
        return;
    }
    if (preg_match('#^food/coupons/(\d+)/print$#', $path, $m) === 1 && $method === 'GET') {
        action_print_coupons((int) $m[1]);
        return;
    }
    if ($path === 'vastra') {
        action_vastra($method);
        return;
    }
    if ($path === 'donations') {
        action_donations($method);
        return;
    }
    if (preg_match('#^donations/(\d+)/generate_receipt$#', $path, $m) === 1 && $method === 'POST') {
        action_generate_receipt((int) $m[1]);
        return;
    }
    if ($path === 'receipts' && $method === 'GET') {
        action_receipts();
        return;
    }
    if ($path === 'receipts/download' && $method === 'POST') {
        action_receipts_download();
        return;
    }
    if ($path === 'receipts/delete' && $method === 'POST') {
        action_receipts_delete();
        return;
    }
    if (preg_match('#^receipts/([A-Za-z0-9._-]+)$#', $path, $m) === 1 && $method === 'GET') {
        action_serve_receipt($m[1]);
        return;
    }
    if ($path === 'expenses') {
        action_expenses($method);
        return;
    }
    if ($path === 'bank') {
        action_bank($method);
        return;
    }
    if ($path === 'bank/manual_match' && $method === 'POST') {
        action_bank_match();
        return;
    }
    if ($path === 'reports') {
        action_reports();
        return;
    }
    if (preg_match('#^reports/([a-z]+)$#', $path, $m) === 1 && $method === 'GET') {
        action_report($m[1]);
        return;
    }
    if ($path === 'subscriptions') {
        action_subscriptions($method);
        return;
    }
    if ($path === 'subscriptions/bulk_send' && $method === 'POST') {
        action_bulk_send();
        return;
    }
    if (preg_match('#^subscriptions/(\d+)/generate_invoice$#', $path, $m) === 1 && $method === 'POST') {
        action_generate_invoice((int) $m[1]);
        return;
    }
    if (preg_match('#^subscriptions/invoice/(\d+)/send$#', $path, $m) === 1 && $method === 'POST') {
        action_send_invoice((int) $m[1]);
        return;
    }
    if ($path === 'demo') {
        action_demo();
        return;
    }
    if ($path === 'users') {
        action_users($method);
        return;
    }
    if (preg_match('#^users/(\d+)/toggle$#', $path, $m) === 1 && $method === 'POST') {
        action_toggle_user((int) $m[1]);
        return;
    }
    if ($path === 'api/donors/search' && $method === 'GET') {
        action_donor_search();
        return;
    }

    http_response_code(404);
    echo 'Page not found.';
}

function action_login(string $method): void
{
    if (isset($_SESSION['user_id']) && $method === 'GET') {
        redirect(url(''));
    }
    if ($method === 'POST') {
        $username = post_string('username', 50);
        $password = (string) ($_POST['password'] ?? '');
        $user = db_one('SELECT * FROM users WHERE username = ? AND is_active = 1', [$username]);
        if ($user !== null && password_verify($password, (string) $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['full_name'] = (string) $user['full_name'];
            $_SESSION['role'] = (string) $user['role'];
            $next = $_GET['next'] ?? $_POST['next'] ?? null;
            redirect(safe_next(is_string($next) ? $next : null));
        }
        flash('error', 'Invalid username or password.');
    }
    $next = $_GET['next'] ?? '';
    render('login', [
        'title' => 'Login',
        'next' => is_string($next) ? $next : '',
    ], false);
}

function action_dashboard(): void
{
    login_required();
    render('dashboard', [
        'title' => 'Dashboard',
        'pageTitle' => 'Dashboard',
        'active' => 'dashboard',
        'summary' => dashboard_summary(db()),
        'recentDonations' => db_all(
            'SELECT d.*, don.name AS donor_name FROM donations d
             JOIN donors don ON d.donor_id = don.id
             ORDER BY d.donation_date DESC LIMIT 5'
        ),
        'recentExpenses' => db_all('SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 5'),
        'lowStockItems' => db_all('SELECT * FROM food_items WHERE current_stock <= minimum_threshold'),
    ]);
}

function action_inventory(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $name = post_string('name', 150);
        $category = one_of(post_string('category', 50), INVENTORY_CATEGORIES, 'Other');
        $qty = max(0, (int) ($_POST['quantity'] ?? 0));
        if ($name === '') {
            flash('error', 'Item name is required.');
            redirect(url('inventory'));
        }
        db_exec(
            'INSERT INTO inventory_items (category, name, description, quantity, unit, item_condition, location, source, added_date, added_by, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $category,
                $name,
                post_string('description', 2000) ?: null,
                $qty,
                post_string('unit', 30) ?: 'pcs',
                one_of(post_string('item_condition', 20), ['New', 'Good', 'Fair', 'Needs Repair', 'Damaged'], 'Good'),
                post_string('location', 100) ?: null,
                one_of(post_string('source', 20), ['Purchased', 'Donated'], 'Purchased'),
                date('Y-m-d'),
                (int) $_SESSION['user_id'],
                post_string('notes', 2000) ?: null,
            ]
        );
        flash('success', 'Inventory item added.');
        redirect(url('inventory'));
    }
    render('inventory', [
        'title' => 'Inventory',
        'pageTitle' => 'Inventory Management',
        'active' => 'inventory',
        'items' => db_all('SELECT * FROM inventory_items ORDER BY category, name'),
        'categories' => INVENTORY_CATEGORIES,
    ]);
}

function action_food(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $action = post_string('action', 20);
        $pdo = db();
        if ($action === 'new_item') {
            $name = post_string('name', 150);
            if ($name === '') {
                flash('error', 'Food item name is required.');
                redirect(url('food'));
            }
            db_exec(
                'INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)',
                [
                    $name,
                    post_string('unit', 30) ?: 'kg',
                    (float) ($_POST['current_stock'] ?? 0),
                    (float) ($_POST['minimum_threshold'] ?? 0),
                ]
            );
            flash('success', 'Food item added to stock list.');
        } elseif ($action === 'add_stock' || $action === 'use_stock') {
            $foodId = (int) ($_POST['food_item_id'] ?? 0);
            $qty = (float) ($_POST['quantity'] ?? 0);
            $item = db_one('SELECT id FROM food_items WHERE id = ?', [$foodId]);
            if ($item === null || $qty <= 0) {
                flash('error', 'Choose an item and a quantity greater than zero.');
                redirect(url('food'));
            }
            $txnType = $action === 'add_stock' ? 'Added' : 'Used';
            $delta = $txnType === 'Added' ? $qty : -$qty;
            $pdo->beginTransaction();
            db_exec(
                'UPDATE food_items SET current_stock = current_stock + ?, last_updated = ? WHERE id = ?',
                [$delta, date('Y-m-d H:i:s'), $foodId]
            );
            db_exec(
                'INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)',
                [$foodId, $txnType, $qty, post_string('purpose', 200) ?: null, date('Y-m-d'), (int) $_SESSION['user_id']]
            );
            $pdo->commit();
            flash('success', $txnType === 'Added' ? 'Stock added.' : 'Stock usage logged.');
        }
        redirect(url('food'));
    }
    render('food', [
        'title' => 'Food Stock',
        'pageTitle' => 'Raw Food Items & Usage',
        'active' => 'food',
        'items' => db_all('SELECT * FROM food_items ORDER BY name'),
        'logs' => db_all(
            'SELECT l.*, f.name AS food_name, f.unit FROM food_usage_log l
             JOIN food_items f ON l.food_item_id = f.id
             ORDER BY l.txn_date DESC, l.id DESC LIMIT 30'
        ),
    ]);
}

function action_food_coupons(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $name = post_string('coupon_name', 100);
        $cost = (float) ($_POST['cost'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        if ($name === '' || $cost <= 0 || $quantity < 1 || $quantity > 400) {
            flash('error', 'Enter a coupon name, a cost above zero, and a quantity from 1 to 400.');
            redirect(url('food/coupons'));
        }
        $last = db_value('SELECT MAX(end_sl_no) FROM food_coupon_batches');
        $start = ($last === null || $last === false) ? 1 : ((int) $last) + 1;
        $end = $start + $quantity - 1;
        $total = $cost * $quantity;
        $batchId = db_exec(
            'INSERT INTO food_coupon_batches (coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, created_date, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [$name, $cost, $start, $end, $quantity, $total, date('Y-m-d'), (int) $_SESSION['user_id']]
        );
        generate_coupon_batch_pdf($batchId, $name, $cost, $start, $quantity);
        flash('success', sprintf(
            'Generated %d coupons for “%s” — Sl No %d to %d (total value %s).',
            $quantity,
            $name,
            $start,
            $end,
            money($total, 0)
        ));
        redirect(url('food/coupons'));
    }
    $batches = db_all(
        'SELECT b.*, u.full_name AS created_by_name FROM food_coupon_batches b
         LEFT JOIN users u ON b.created_by = u.id ORDER BY b.id DESC'
    );
    $totalValue = (float) db_value('SELECT COALESCE(SUM(total_value),0) FROM food_coupon_batches');
    $totalQty = 0;
    foreach ($batches as $batch) {
        $totalQty += (int) $batch['quantity'];
    }
    render('food_coupons', [
        'title' => 'Food Coupons',
        'pageTitle' => 'Food Coupon Generator',
        'active' => 'food',
        'batches' => $batches,
        'totalCouponsValue' => $totalValue,
        'totalCouponQty' => $totalQty,
    ]);
}

function action_print_coupons(int $batchId): void
{
    login_required();
    $path = APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
    if (!is_file($path)) {
        $batch = db_one('SELECT * FROM food_coupon_batches WHERE id = ?', [$batchId]);
        if ($batch === null) {
            flash('error', 'Coupon batch not found.');
            redirect(url('food/coupons'));
        }
        generate_coupon_batch_pdf(
            $batchId,
            (string) $batch['coupon_name'],
            (float) $batch['cost'],
            (int) $batch['start_sl_no'],
            (int) $batch['quantity']
        );
    }
    send_pdf($path, 'batch_' . $batchId . '.pdf');
}

function action_vastra(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $deity = one_of(post_string('deity_name', 100), ['Jagannath', 'Balabhadra', 'Subhadra', 'Sudarshan'], 'Jagannath');
        $item = post_string('item_name', 150);
        if ($item === '') {
            flash('error', 'Item name is required.');
            redirect(url('vastra'));
        }
        db_exec(
            'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status, notes)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $deity,
                $item,
                post_string('color', 50) ?: null,
                max(1, (int) ($_POST['quantity'] ?? 1)),
                one_of(post_string('source', 20), ['Purchased', 'Donated'], 'Purchased'),
                date('Y-m-d'),
                one_of(post_string('status', 20), ['In Store', 'In Use', 'Retired'], 'In Store'),
                post_string('notes', 500) ?: null,
            ]
        );
        flash('success', 'Vastra item added.');
        redirect(url('vastra'));
    }
    render('vastra', [
        'title' => 'Deity Vastra',
        'pageTitle' => 'Deity Vastra (Cloths) Management',
        'active' => 'vastra',
        'items' => db_all('SELECT * FROM vastra_items ORDER BY deity_name, item_name'),
    ]);
}

function action_donations(string $method): void
{
    login_required();
    if ($method === 'POST') {
        record_donation();
        redirect(url('donations'));
    }
    render('donations', [
        'title' => 'Donations',
        'pageTitle' => 'Donations',
        'active' => 'donations',
        'donations' => db_all(
            'SELECT d.*, don.name AS donor_name, don.phone AS donor_phone FROM donations d
             JOIN donors don ON d.donor_id = don.id ORDER BY d.donation_date DESC'
        ),
        'foodItems' => db_all('SELECT * FROM food_items ORDER BY name'),
        'today' => date('Y-m-d'),
    ]);
}

function record_donation(): void
{
    $name = post_string('donor_name', 150);
    if ($name === '') {
        flash('error', 'Donor name is required.');
        return;
    }
    $phone = post_string('donor_phone', 20);
    $type = one_of(post_string('donation_type', 20), ['Cash', 'Food', 'Vastra', 'Inventory', 'Other'], 'Cash');
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $amount = $amountRaw === '' ? null : (float) $amountRaw;
    $paymentMode = one_of(
        post_string('payment_mode', 20),
        ['Cash', 'Bank Transfer', 'UPI', 'Cheque', 'In-Kind', 'Card', 'Netbanking'],
        $type === 'Cash' ? 'Cash' : 'In-Kind'
    );
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $donor = null;
        if ($phone !== '') {
            $donor = db_one('SELECT * FROM donors WHERE phone = ?', [$phone]);
        }
        if ($donor === null) {
            $donorId = db_exec(
                'INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)',
                [
                    $name,
                    $phone !== '' ? $phone : null,
                    post_string('donor_email', 120) ?: null,
                    post_string('donor_address', 500) ?: null,
                    post_string('pan_number', 20) ?: null,
                ]
            );
        } else {
            $donorId = (int) $donor['id'];
        }
        $donationId = db_exec(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $donorId,
                $type,
                $amount,
                post_string('purpose', 200) ?: 'General',
                post_date('donation_date'),
                $paymentMode,
                (int) $_SESSION['user_id'],
            ]
        );
        if ($type === 'Food') {
            $foodId = (int) ($_POST['food_item_id'] ?? 0);
            $qty = (float) ($_POST['food_quantity'] ?? 0);
            if ($foodId > 0 && db_one('SELECT id FROM food_items WHERE id = ?', [$foodId]) !== null) {
                db_exec('UPDATE food_items SET current_stock = current_stock + ? WHERE id = ?', [$qty, $foodId]);
                db_exec(
                    'INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)',
                    [$foodId, 'Added', $qty, 'Donation from ' . $name, date('Y-m-d'), (int) $_SESSION['user_id']]
                );
                db_exec('UPDATE donations SET linked_food_id = ? WHERE id = ?', [$foodId, $donationId]);
            }
        } elseif ($type === 'Vastra') {
            $deity = post_string('vastra_deity', 100);
            if ($deity !== '') {
                $vastraId = db_exec(
                    'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, donation_id, date_added, status)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $deity,
                        post_string('vastra_item_name', 150) ?: 'Vastra',
                        post_string('vastra_color', 50) ?: null,
                        max(1, (int) ($_POST['vastra_quantity'] ?? 1)),
                        'Donated',
                        $donationId,
                        date('Y-m-d'),
                        'In Store',
                    ]
                );
                db_exec('UPDATE donations SET linked_vastra_id = ? WHERE id = ?', [$vastraId, $donationId]);
            }
        } elseif ($type === 'Inventory') {
            $itemName = post_string('inventory_name', 150);
            if ($itemName !== '') {
                $inventoryId = db_exec(
                    'INSERT INTO inventory_items (category, name, quantity, unit, source, donation_id, added_date, added_by, notes)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        post_string('inventory_category', 50) ?: 'Other',
                        $itemName,
                        max(1, (int) ($_POST['inventory_quantity'] ?? 1)),
                        post_string('inventory_unit', 30) ?: 'pcs',
                        'Donated',
                        $donationId,
                        date('Y-m-d'),
                        (int) $_SESSION['user_id'],
                        'Donated by ' . $name,
                    ]
                );
                db_exec('UPDATE donations SET linked_inventory_id = ? WHERE id = ?', [$inventoryId, $donationId]);
            }
        }
        $pdo->commit();
        flash('success', 'Donation recorded.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] donation: ' . $e->getMessage());
        flash('error', 'Could not record the donation.');
    }
}

function action_generate_receipt(int $donationId): void
{
    login_required();
    $pdo = db();
    $donation = db_one('SELECT * FROM donations WHERE id = ?', [$donationId]);
    if ($donation === null) {
        flash('error', 'Donation not found.');
        redirect(url('donations'));
    }
    $donor = db_one('SELECT * FROM donors WHERE id = ?', [(int) $donation['donor_id']]);
    if ($donor === null) {
        flash('error', 'Donor not found.');
        redirect(url('donations'));
    }
    $pdo->beginTransaction();
    try {
        $receiptNumber = $donation['receipt_number'] ?: next_receipt_number($pdo);
        generate_receipt_pdf($donation, $donor, (string) $receiptNumber);
        db_exec(
            'UPDATE donations SET receipt_number = ?, receipt_generated = 1 WHERE id = ?',
            [$receiptNumber, $donationId]
        );
        db_exec(
            'INSERT IGNORE INTO receipts (donation_id, receipt_number, generated_by) VALUES (?,?,?)',
            [$donationId, $receiptNumber, (int) $_SESSION['user_id']]
        );
        $pdo->commit();
        flash('success', 'Receipt ' . $receiptNumber . ' generated.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] receipt: ' . $e->getMessage());
        flash('error', 'Could not generate the receipt.');
    }
    redirect(url('donations'));
}

function action_serve_receipt(string $filename): void
{
    login_required();
    $filename = basename($filename);
    if (preg_match('/^RCPT-\d{4}-\d{4}\.pdf$/', $filename) !== 1) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $path = APP_ROOT . '/storage/receipts/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $download = ($_GET['download'] ?? '') === '1';
    send_pdf($path, $filename, $download);
}

function action_receipts(): void
{
    login_required();
    $rows = receipt_rows();
    $onDisk = 0;
    foreach ($rows as $row) {
        if (receipt_file_exists((string) $row['receipt_number'])) {
            $onDisk++;
        }
    }
    render('receipts', [
        'title' => 'Receipts',
        'pageTitle' => 'Receipts',
        'active' => 'receipts',
        'receipts' => $rows,
        'onDisk' => $onDisk,
        'isAdmin' => ($_SESSION['role'] ?? '') === 'Admin',
    ]);
}

function action_receipts_download(): void
{
    admin_required();
    $rows = post_string('scope', 10) === 'all' ? receipt_rows() : selected_receipt_rows();
    if ($rows === []) {
        flash('error', 'Select at least one receipt to download.');
        redirect(url('receipts'));
    }
    try {
        $zip = zip_receipt_files($rows);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect(url('receipts'));
    }
    $name = 'receipts-' . date('Ymd-His') . '.zip';
    send_file($zip, $name, 'application/zip', true);
}

function action_receipts_delete(): void
{
    admin_required();
    $rows = selected_receipt_rows();
    if ($rows === []) {
        flash('error', 'Select at least one receipt to delete.');
        redirect(url('receipts'));
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            db_exec('DELETE FROM receipts WHERE donation_id = ?', [(int) $row['donation_id']]);
            db_exec(
                'UPDATE donations SET receipt_number = NULL, receipt_generated = 0 WHERE id = ?',
                [(int) $row['donation_id']]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] receipt delete: ' . $e->getMessage());
        flash('error', 'Could not delete the selected receipts.');
        redirect(url('receipts'));
    }
    foreach ($rows as $row) {
        $path = receipt_path((string) $row['receipt_number']);
        if (is_file($path)) {
            unlink($path);
        }
    }
    flash('success', count($rows) . ' receipt' . (count($rows) === 1 ? '' : 's') . ' deleted. Those donations can have a new receipt generated.');
    redirect(url('receipts'));
}

/** @return list<array<string, mixed>> */
function receipt_rows(): array
{
    return db_all(
        "SELECT d.id AS donation_id, d.receipt_number, d.amount, d.donation_date, d.donation_type,
                d.purpose, d.payment_mode,
                don.name AS donor_name, don.phone AS donor_phone,
                r.generated_date, u.full_name AS generated_by_name
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         LEFT JOIN receipts r ON r.donation_id = d.id
         LEFT JOIN users u ON r.generated_by = u.id
         WHERE d.receipt_generated = 1
           AND d.receipt_number IS NOT NULL
           AND d.receipt_number <> ''
         ORDER BY d.donation_date DESC, d.id DESC"
    );
}

/** @return list<array<string, mixed>> */
function selected_receipt_rows(): array
{
    $raw = $_POST['donation_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return db_all(
        "SELECT d.id AS donation_id, d.receipt_number
         FROM donations d
         WHERE d.receipt_generated = 1
           AND d.receipt_number IS NOT NULL
           AND d.id IN ($placeholders)",
        $ids
    );
}

function receipt_path(string $receiptNumber): string
{
    return APP_ROOT . '/storage/receipts/' . $receiptNumber . '.pdf';
}

function receipt_file_exists(string $receiptNumber): bool
{
    return preg_match('/^RCPT-\d{4}-\d{4}$/', $receiptNumber) === 1 && is_file(receipt_path($receiptNumber));
}

/** @param list<array<string, mixed>> $rows */
function zip_receipt_files(array $rows): string
{
    $dir = APP_ROOT . '/storage/receipts';
    $names = [];
    foreach ($rows as $row) {
        $number = (string) ($row['receipt_number'] ?? '');
        if (receipt_file_exists($number)) {
            $names[] = $number . '.pdf';
        }
    }
    $names = array_values(array_unique($names));
    if ($names === []) {
        throw new RuntimeException('None of those receipts have a PDF file yet.');
    }
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipts_' . bin2hex(random_bytes(4)) . '.zip';
    $command = 'tar -a -c -f ' . escapeshellarg($tmp) . ' -C ' . escapeshellarg($dir) . ' '
        . implode(' ', array_map('escapeshellarg', $names));
    exec($command, $output, $code);
    if ($code !== 0 || !is_file($tmp)) {
        if (is_file($tmp)) {
            unlink($tmp);
        }
        throw new RuntimeException('Could not build the zip download.');
    }
    return $tmp;
}

function action_expenses(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $category = one_of(post_string('category', 80), EXPENSE_CATEGORIES, 'Other');
        if ($amount <= 0) {
            flash('error', 'Enter an amount greater than zero.');
            redirect(url('expenses'));
        }
        db_exec(
            'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, receipt_ref, added_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $category,
                post_string('description', 255) ?: null,
                $amount,
                post_string('paid_to', 150) ?: null,
                post_date('expense_date'),
                one_of(post_string('payment_mode', 20), ['Cash', 'Bank Transfer', 'UPI', 'Cheque'], 'Cash'),
                post_string('receipt_ref', 100) ?: null,
                (int) $_SESSION['user_id'],
            ]
        );
        flash('success', 'Expense recorded.');
        redirect(url('expenses'));
    }
    render('expenses', [
        'title' => 'Expenses',
        'pageTitle' => 'Expense Management',
        'active' => 'expenses',
        'expenses' => db_all('SELECT * FROM expenses ORDER BY expense_date DESC'),
        'categories' => EXPENSE_CATEGORIES,
        'today' => date('Y-m-d'),
    ]);
}

function action_bank(string $method): void
{
    login_required();
    if ($method === 'POST') {
        try {
            $file = $_FILES['statement_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Please choose a file to upload.');
            }
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('The upload did not complete.');
            }
            if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('File is larger than 5 MB.');
            }
            $original = basename((string) ($file['name'] ?? 'statement.csv'));
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'xlsx'], true)) {
                throw new RuntimeException('Upload a .csv or .xlsx file.');
            }
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?? ('statement.' . $ext);
            $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;
            $dest = APP_ROOT . '/storage/uploads/' . $stored;
            if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
                throw new RuntimeException('Could not store the uploaded file.');
            }
            $rows = parse_statement_file($dest);
            $pdo = db();
            $pdo->beginTransaction();
            $batchId = db_exec(
                'INSERT INTO bank_statement_uploads (filename, uploaded_by, total_transactions) VALUES (?,?,?)',
                [$safe, (int) $_SESSION['user_id'], count($rows)]
            );
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = db_exec(
                    'INSERT INTO bank_transactions (upload_batch_id, txn_date, description, amount, txn_type, balance)
                     VALUES (?,?,?,?,?,?)',
                    [$batchId, $row['txn_date'], $row['description'], $row['amount'], $row['txn_type'], $row['balance']]
                );
            }
            $matched = reconcile_transactions($pdo, $ids);
            db_exec('UPDATE bank_statement_uploads SET matched_count = ? WHERE id = ?', [$matched, $batchId]);
            $pdo->commit();
            flash('success', sprintf(
                'Uploaded %d transactions — %d auto-matched, %d need review.',
                count($rows),
                $matched,
                count($rows) - $matched
            ));
        } catch (RuntimeException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('[jt_blr] bank: ' . $e->getMessage());
            flash('error', 'Could not parse the statement.');
        }
        redirect(url('bank'));
    }
    render('bank', [
        'title' => 'Bank & Reconciliation',
        'pageTitle' => 'Bank Statement & Reconciliation',
        'active' => 'bank',
        'uploads' => db_all('SELECT * FROM bank_statement_uploads ORDER BY upload_date DESC'),
        'unmatched' => db_all("SELECT * FROM bank_transactions WHERE reconciled_status = 'Unmatched' ORDER BY txn_date DESC"),
        'matched' => db_all(
            "SELECT bt.*, d.receipt_number, don.name AS donor_name, e.description AS expense_desc
             FROM bank_transactions bt
             LEFT JOIN donations d ON bt.matched_donation_id = d.id
             LEFT JOIN donors don ON d.donor_id = don.id
             LEFT JOIN expenses e ON bt.matched_expense_id = e.id
             WHERE bt.reconciled_status IN ('Matched','Manual') ORDER BY bt.txn_date DESC"
        ),
        'openDonations' => db_all(
            'SELECT d.id, don.name, d.amount, d.donation_date FROM donations d
             JOIN donors don ON d.donor_id = don.id
             WHERE d.reconciled_bank_txn_id IS NULL AND d.amount IS NOT NULL'
        ),
        'openExpenses' => db_all(
            'SELECT id, description, amount, expense_date FROM expenses WHERE reconciled_bank_txn_id IS NULL'
        ),
    ]);
}

function action_bank_match(): void
{
    login_required();
    $txnId = (int) ($_POST['txn_id'] ?? 0);
    $matchType = post_string('match_type', 20);
    $matchId = (int) ($_POST['match_id'] ?? 0);
    $txn = db_one("SELECT * FROM bank_transactions WHERE id = ? AND reconciled_status = 'Unmatched'", [$txnId]);
    if ($txn === null || $matchId < 1) {
        flash('error', 'That transaction cannot be linked.');
        redirect(url('bank'));
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($matchType === 'donation' && $txn['txn_type'] === 'Credit') {
            $donation = db_one(
                'SELECT id FROM donations WHERE id = ? AND reconciled_bank_txn_id IS NULL AND amount IS NOT NULL',
                [$matchId]
            );
            if ($donation === null) {
                throw new RuntimeException('Choose an open donation.');
            }
            db_exec(
                "UPDATE bank_transactions SET reconciled_status = 'Manual', matched_donation_id = ? WHERE id = ?",
                [$matchId, $txnId]
            );
            db_exec('UPDATE donations SET reconciled_bank_txn_id = ? WHERE id = ?', [$txnId, $matchId]);
        } elseif ($matchType === 'expense' && $txn['txn_type'] === 'Debit') {
            $expense = db_one('SELECT id FROM expenses WHERE id = ? AND reconciled_bank_txn_id IS NULL', [$matchId]);
            if ($expense === null) {
                throw new RuntimeException('Choose an open expense.');
            }
            db_exec(
                "UPDATE bank_transactions SET reconciled_status = 'Manual', matched_expense_id = ? WHERE id = ?",
                [$matchId, $txnId]
            );
            db_exec('UPDATE expenses SET reconciled_bank_txn_id = ? WHERE id = ?', [$txnId, $matchId]);
        } else {
            throw new RuntimeException('That link type does not match the transaction.');
        }
        $pdo->commit();
        flash('success', 'Transaction linked manually.');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] match: ' . $e->getMessage());
        flash('error', 'Could not link the transaction.');
    }
    redirect(url('bank'));
}

function action_reports(): void
{
    login_required();
    render('reports', [
        'title' => 'Reports',
        'pageTitle' => 'Reports',
        'active' => 'reports',
    ]);
}

function action_report(string $type): void
{
    login_required();
    $titles = [
        'donations' => 'Donation Report',
        'expenses' => 'Expense Report',
        'inventory' => 'Inventory Report',
        'food' => 'Food Stock Report',
        'vastra' => 'Deity Vastra Report',
        'reconciliation' => 'Bank Reconciliation Report',
    ];
    if (!isset($titles[$type])) {
        http_response_code(404);
        echo 'Unknown report.';
        return;
    }
    $start = optional_date(isset($_GET['start']) && is_string($_GET['start']) ? $_GET['start'] : null);
    $end = optional_date(isset($_GET['end']) && is_string($_GET['end']) ? $_GET['end'] : null);
    $data = match ($type) {
        'donations' => donation_report($start, $end),
        'expenses' => expense_report($start, $end),
        'inventory' => inventory_report(),
        'food' => food_stock_report(),
        'vastra' => vastra_report(),
        'reconciliation' => reconciliation_report(),
    };
    render('report', [
        'title' => $titles[$type],
        'reportType' => $type,
        'data' => $data,
        'start' => $start,
        'end' => $end,
        'generatedOn' => date('d-M-Y H:i'),
    ], false);
}

function action_subscriptions(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $name = post_string('name', 150);
        $mobile = post_string('mobile', 15);
        $amount = (float) ($_POST['plan_amount'] ?? 0);
        if ($name === '' || $mobile === '' || $amount <= 0) {
            flash('error', 'Name, mobile, and amount are required.');
            redirect(url('subscriptions'));
        }
        try {
            db_exec(
                'INSERT INTO subscribers (name, mobile, email, plan_name, plan_amount, frequency, status, start_date)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $name,
                    $mobile,
                    post_string('email', 120) ?: null,
                    one_of(post_string('plan_name', 100), PLAN_PRESETS, PLAN_PRESETS[0]),
                    $amount,
                    one_of(post_string('frequency', 20), ['Monthly', 'Quarterly', 'Yearly'], 'Monthly'),
                    'Active',
                    date('Y-m-d'),
                ]
            );
            flash('success', 'Subscriber added.');
        } catch (PDOException $e) {
            $message = str_contains($e->getMessage(), 'Duplicate')
                ? 'That mobile number is already registered.'
                : 'Could not add the subscriber.';
            flash('error', $message);
        }
        redirect(url('subscriptions'));
    }
    render('subscriptions', [
        'title' => 'Subscriptions',
        'pageTitle' => 'Monthly Subscriptions',
        'active' => 'subscriptions',
        'subs' => db_all(
            "SELECT s.*,
                (SELECT COUNT(*) FROM subscription_invoices i WHERE i.subscriber_id = s.id AND i.status = 'Paid') AS paid_count,
                (SELECT COUNT(*) FROM subscription_invoices i WHERE i.subscriber_id = s.id AND i.status IN ('Sent','Pending','Overdue')) AS due_count
             FROM subscribers s ORDER BY (s.status = 'Active') DESC, s.name"
        ),
        'invoices' => db_all(
            "SELECT i.*, s.name AS subscriber_name, s.mobile, s.email FROM subscription_invoices i
             JOIN subscribers s ON i.subscriber_id = s.id
             ORDER BY (i.status = 'Overdue') DESC, (i.status = 'Sent') DESC, (i.status = 'Pending') DESC, i.due_date DESC"
        ),
        'planPresets' => PLAN_PRESETS,
        'mrr' => (float) db_value("SELECT COALESCE(SUM(plan_amount),0) FROM subscribers WHERE status = 'Active' AND frequency = 'Monthly'"),
        'pendingAmount' => (float) db_value("SELECT COALESCE(SUM(amount),0) FROM subscription_invoices WHERE status IN ('Sent','Pending','Overdue')"),
    ]);
}

function action_generate_invoice(int $subId): void
{
    login_required();
    $sub = db_one('SELECT * FROM subscribers WHERE id = ?', [$subId]);
    if ($sub === null) {
        flash('error', 'Subscriber not found.');
        redirect(url('subscriptions'));
    }
    $year = date('Y');
    $last = db_one(
        'SELECT invoice_number FROM subscription_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1',
        ["INV-{$year}-%"]
    );
    $seq = 1;
    if ($last !== null) {
        $parts = explode('-', (string) $last['invoice_number']);
        $seq = ((int) end($parts)) + 1;
    }
    $number = sprintf('INV-%s-%04d', $year, $seq);
    db_exec(
        "INSERT INTO subscription_invoices (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token)
         VALUES (?,?,?,?,?,'Pending',?)",
        [$subId, $number, $sub['plan_amount'], date('F Y'), date('Y-m-d'), random_token()]
    );
    flash('success', 'Invoice ' . $number . ' created for ' . $sub['name'] . '.');
    redirect(url('subscriptions'));
}

function action_send_invoice(int $invoiceId): void
{
    login_required();
    $inv = notify_invoice($invoiceId);
    if ($inv === null) {
        flash('error', 'Invoice not found.');
        redirect(url('subscriptions'));
    }
    $extra = !empty($inv['email']) ? ' and ' . $inv['email'] : '';
    flash('success', 'Invoice ' . $inv['invoice_number'] . ' sent to ' . $inv['mobile'] . $extra . ' (simulated — see storage/logs/notifications.log).');
    redirect(url('subscriptions'));
}

function action_bulk_send(): void
{
    login_required();
    $ids = $_POST['invoice_ids'] ?? [];
    if (!is_array($ids) || $ids === []) {
        flash('error', 'No invoices selected.');
        redirect(url('subscriptions'));
    }
    $sent = 0;
    foreach ($ids as $id) {
        if (notify_invoice((int) $id) !== null) {
            $sent++;
        }
    }
    flash('success', 'Sent ' . $sent . ' of ' . count($ids) . ' selected invoice(s). Messages are logged in storage/logs/notifications.log.');
    redirect(url('subscriptions'));
}

function notify_invoice(int $invoiceId): ?array
{
    $inv = db_one(
        'SELECT i.*, s.name, s.mobile, s.email FROM subscription_invoices i
         JOIN subscribers s ON i.subscriber_id = s.id WHERE i.id = ?',
        [$invoiceId]
    );
    if ($inv === null || $inv['status'] === 'Paid') {
        return null;
    }
    $payUrl = absolute_url('pay/' . $inv['payment_token']);
    $message = sprintf(
        'Namaskar %s, your %s seva contribution of Rs.%s is due. Pay securely here: %s — Shree Jagannath Temple',
        $inv['name'],
        (string) $inv['period_label'],
        number_format((float) $inv['amount'], 0),
        $payUrl
    );
    notify_log('SMS', (string) $inv['mobile'], $message);
    if (!empty($inv['email'])) {
        notify_log('EMAIL', (string) $inv['email'], 'Seva Contribution Due — ' . $inv['invoice_number'] . ' | ' . $message);
    }
    db_exec(
        "UPDATE subscription_invoices SET status = 'Sent', notification_sent = 1, notification_sent_at = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $invoiceId]
    );
    return $inv;
}

function action_pay(string $token): void
{
    $inv = find_invoice_by_token($token);
    if ($inv === null) {
        http_response_code(404);
        render('pay_invalid', [], false);
        return;
    }
    render('pay_invoice', ['inv' => $inv], false);
}

function action_pay_confirm(string $token): void
{
    $inv = find_invoice_by_token($token);
    if ($inv === null || $inv['status'] === 'Paid') {
        http_response_code(404);
        render('pay_invalid', [], false);
        return;
    }
    $paidVia = one_of(post_string('payment_method', 30), ['UPI', 'Card', 'Netbanking'], 'UPI');
    $ref = 'PAY-REF-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $donor = db_one('SELECT id FROM donors WHERE phone = ?', [$inv['mobile']]);
        if ($donor === null) {
            $donorId = db_exec(
                'INSERT INTO donors (name, phone) VALUES (?,?)',
                [$inv['name'], $inv['mobile']]
            );
        } else {
            $donorId = (int) $donor['id'];
        }
        $donationMode = $paidVia === 'Netbanking' ? 'Netbanking' : ($paidVia === 'Card' ? 'Card' : 'UPI');
        $donationId = db_exec(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_generated, created_by)
             VALUES (?,?,?,?,?,?,0,1)',
            [
                $donorId,
                'Cash',
                $inv['amount'],
                'Subscription — ' . $inv['plan_name'] . ' (' . $inv['period_label'] . ')',
                date('Y-m-d'),
                $donationMode,
            ]
        );
        db_exec(
            "UPDATE subscription_invoices
             SET status = 'Paid', paid_date = ?, payment_reference = ?, paid_via = ?, linked_donation_id = ?
             WHERE id = ? AND status <> 'Paid'",
            [date('Y-m-d H:i:s'), $ref, $paidVia, $donationId, (int) $inv['id']]
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] pay: ' . $e->getMessage());
        http_response_code(500);
        echo 'Payment could not be recorded.';
        return;
    }
    render('pay_success', ['inv' => $inv, 'ref' => $ref], false);
}

function find_invoice_by_token(string $token): ?array
{
    if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token) !== 1) {
        return null;
    }
    return db_one(
        'SELECT i.*, s.name, s.mobile, s.plan_name FROM subscription_invoices i
         JOIN subscribers s ON i.subscriber_id = s.id WHERE i.payment_token = ?',
        [$token]
    );
}

function action_demo(): void
{
    login_required();
    render('demo', [
        'summary' => dashboard_summary(db()),
        'recentDonations' => db_all(
            'SELECT d.*, don.name AS donor_name FROM donations d
             JOIN donors don ON d.donor_id = don.id
             ORDER BY d.donation_date DESC LIMIT 8'
        ),
        'recentInvoices' => db_all(
            "SELECT i.*, s.name AS subscriber_name, s.plan_name FROM subscription_invoices i
             JOIN subscribers s ON i.subscriber_id = s.id
             ORDER BY (i.status = 'Overdue') DESC, (i.status = 'Sent') DESC, i.due_date DESC LIMIT 8"
        ),
        'inventorySample' => db_all('SELECT * FROM inventory_items ORDER BY added_date DESC LIMIT 8'),
        'foodSample' => db_all('SELECT * FROM food_items ORDER BY name LIMIT 8'),
        'vastraSample' => db_all('SELECT * FROM vastra_items ORDER BY date_added DESC LIMIT 8'),
        'expenseSample' => db_all('SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 8'),
        'bankSample' => db_all('SELECT * FROM bank_transactions ORDER BY txn_date DESC LIMIT 8'),
        'generatedOn' => date('d M Y, h:i A'),
    ], false);
}

function action_users(string $method): void
{
    admin_required();
    if ($method === 'POST') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $activeCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1 FOR UPDATE')->fetchColumn();
            if ($activeCount >= MAX_ADMIN_USERS) {
                $pdo->rollBack();
                flash('error', 'User limit reached (' . MAX_ADMIN_USERS . ' active users max). Deactivate someone first.');
                redirect(url('users'));
            }
            $username = post_string('username', 50);
            $fullName = post_string('full_name', 100);
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || $fullName === '' || strlen($password) < 6) {
                $pdo->rollBack();
                flash('error', 'Name, username, and a password of at least 6 characters are required.');
                redirect(url('users'));
            }
            db_exec(
                'INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)',
                [
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    one_of(post_string('role', 20), ['Admin', 'Staff'], 'Staff'),
                ]
            );
            $pdo->commit();
            flash('success', 'User added.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'That username already exists.' : 'Could not add the user.');
        }
        redirect(url('users'));
    }
    $users = db_all('SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY created_at');
    $activeCount = 0;
    foreach ($users as $user) {
        if ((int) $user['is_active'] === 1) {
            $activeCount++;
        }
    }
    render('users', [
        'title' => 'Users',
        'pageTitle' => 'Admin Users',
        'active' => 'users',
        'users' => $users,
        'activeCount' => $activeCount,
        'maxUsers' => MAX_ADMIN_USERS,
    ]);
}

function action_toggle_user(int $userId): void
{
    admin_required();
    if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
        flash('error', 'You cannot change your own account from this screen.');
        redirect(url('users'));
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        redirect(url('users'));
    }
    $currentlyActive = (int) $user['is_active'] === 1;
    if ($currentlyActive && $user['role'] === 'Admin') {
        $admins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND is_active = 1");
        if ($admins <= 1) {
            flash('error', 'Cannot deactivate the last active Admin.');
            redirect(url('users'));
        }
    }
    db_exec('UPDATE users SET is_active = ? WHERE id = ?', [$currentlyActive ? 0 : 1, $userId]);
    redirect(url('users'));
}

function action_donor_search(): void
{
    login_required();
    $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
    $q = mb_substr($q, 0, 80);
    $rows = db_all('SELECT id, name, phone FROM donors WHERE name LIKE ? LIMIT 10', ['%' . $q . '%']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
}

function send_pdf(string $path, string $filename, bool $download = false): never
{
    send_file($path, $filename, 'application/pdf', false, $download);
}

function send_file(string $path, string $filename, string $type, bool $deleteAfter = false, bool $download = true): never
{
    header('Content-Type: ' . $type);
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    if ($deleteAfter && is_file($path)) {
        unlink($path);
    }
    exit;
}
