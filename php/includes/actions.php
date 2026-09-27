<?php
declare(strict_types=1);

function dispatch_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();

    if (str_starts_with($path, 'api/coupons')) {
        action_coupon_api($method, $path);
        return;
    }

    if ($method === 'POST') {
        require_csrf();
    }

    if ($path === 'brand/logo' && $method === 'GET') {
        serve_brand_logo();
        return;
    }
    if ($path === 'language' && $method === 'POST') {
        action_language();
        return;
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
    if ($path === 'coupons/scan' && ($method === 'GET' || $method === 'POST')) {
        action_coupon_scan($method);
        return;
    }
    if ($path === 'food/coupons/validate' && $method === 'POST') {
        action_validate_coupon();
        return;
    }
    if ($path === 'food/coupons/invalidate' && $method === 'POST') {
        action_invalidate_coupon();
        return;
    }
    if (preg_match('#^food/coupons/(\d+)/remove$#', $path, $m) === 1 && $method === 'POST') {
        action_remove_coupons((int) $m[1]);
        return;
    }
    if (preg_match('#^food/coupons/(\d+)$#', $path, $m) === 1 && $method === 'POST') {
        action_update_coupons((int) $m[1]);
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
    if (preg_match('#^donations/(\d+)/clear-cheque$#', $path, $m) === 1 && $method === 'POST') {
        action_clear_cheque('donations', (int) $m[1]);
        return;
    }
    if ($path === 'donations') {
        action_donations($method);
        return;
    }
    if (str_starts_with($path, 'invitations')) {
        dispatch_invitations($method, $path);
        return;
    }
    if ($path === 'donors' && $method === 'GET') {
        action_donors();
        return;
    }
    if ($path === 'donors/bulk-email' && $method === 'POST') {
        action_donors_bulk_email();
        return;
    }
    if ($path === 'donors/new') {
        action_devotee_new($method);
        return;
    }
    if (preg_match('#^donors/(\d+)/save$#', $path, $m) === 1 && $method === 'POST') {
        action_devotee_save((int) $m[1]);
        return;
    }
    if (preg_match('#^donors/(\d+)/delete$#', $path, $m) === 1 && $method === 'POST') {
        action_devotee_delete((int) $m[1]);
        return;
    }
    if (preg_match('#^donors/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
        action_donor((int) $m[1]);
        return;
    }
    if (preg_match('#^donors/(\d+)/pledge$#', $path, $m) === 1 && $method === 'POST') {
        action_save_pledge((int) $m[1]);
        return;
    }
    if (preg_match('#^donors/(\d+)/receive$#', $path, $m) === 1 && $method === 'POST') {
        action_receive_pledge((int) $m[1]);
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
    if ($path === 'receipts/cancel' && $method === 'POST') {
        action_receipts_cancel();
        return;
    }
    if ($path === 'receipts/bulk-email' && $method === 'POST') {
        action_receipts_bulk_email();
        return;
    }
    if (preg_match('#^receipts/(\d+)/email$#', $path, $m) === 1 && $method === 'POST') {
        action_receipt_email((int) $m[1]);
        return;
    }
    if (preg_match('#^receipts/open/([a-f0-9]{32})$#', $path, $m) === 1 && $method === 'GET') {
        action_open_receipt($m[1]);
        return;
    }
    if (preg_match('#^receipts/([A-Za-z0-9._-]+)$#', $path, $m) === 1 && $method === 'GET') {
        action_serve_receipt($m[1]);
        return;
    }
    if (preg_match('#^expenses/(\d+)/bill$#', $path, $m) === 1 && $method === 'GET') {
        action_expense_bill((int) $m[1]);
        return;
    }
    if (preg_match('#^expenses/(\d+)/clear-cheque$#', $path, $m) === 1 && $method === 'POST') {
        action_clear_cheque('expenses', (int) $m[1]);
        return;
    }
    if ($path === 'expenses') {
        action_expenses($method);
        return;
    }
    if ($path === 'corrections') {
        action_corrections($method);
        return;
    }
    if ($path === 'approvals' && $method === 'GET') {
        action_approvals();
        return;
    }
    if (preg_match('#^approvals/(\d+)$#', $path, $m) === 1 && $method === 'POST') {
        action_decide_approval((int) $m[1]);
        return;
    }
    if ($path === 'cash-book' && $method === 'GET') {
        action_cash_book();
        return;
    }
    if ($path === 'cash-book/opening' && $method === 'POST') {
        action_save_opening();
        return;
    }
    if ($path === 'cash-book/carry' && $method === 'POST') {
        action_carry_opening();
        return;
    }
    if ($path === 'cash-book/contra' && $method === 'POST') {
        action_save_contra();
        return;
    }
    if ($path === 'day-book' && $method === 'GET') {
        action_day_book();
        return;
    }
    if ($path === 'ledger' && $method === 'GET') {
        action_ledger();
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
    if ($path === 'account/password') {
        action_account_password($method);
        return;
    }
    if (preg_match('#^users/(\d+)/password$#', $path, $m) === 1 && $method === 'POST') {
        action_set_user_password((int) $m[1]);
        return;
    }
    if ($path === 'users') {
        action_users($method);
        return;
    }
    if ($path === 'settings') {
        action_settings($method);
        return;
    }
    if ($path === 'localization') {
        action_localization($method);
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
        flash('error', t('login.invalid'));
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
        'title' => t('page.dashboard'),
        'pageTitle' => t('page.dashboard'),
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
        $kind = post_string('action', 20);
        $userId = (int) $_SESSION['user_id'];
        if ($kind === 'move') {
            $result = record_stock_movement(
                'inventory',
                (int) ($_POST['item_id'] ?? 0),
                post_string('movement_type', 30),
                (float) ($_POST['quantity'] ?? 0),
                post_string('note', 255),
                post_string('movement_date', 10) ?: date('Y-m-d'),
                $userId
            );
            flash($result['error'] !== null ? 'error' : 'success', $result['error'] ?? (
                $result['outcome'] === 'waiting'
                    ? 'That write-off is waiting for approval. Stock is unchanged until then.'
                    : 'Stock movement recorded.'
            ));
            redirect(url('inventory'));
        }
        if ($kind === 'place') {
            $result = save_item_place(
                (int) ($_POST['item_id'] ?? 0),
                post_string('item_condition', 30),
                post_string('location', 100),
                $userId,
                date('Y-m-d')
            );
            flash($result['error'] !== null ? 'error' : 'success', $result['error'] ?? (
                $result['outcome'] === 'waiting'
                    ? 'Retiring this quantity is waiting for approval. The location is updated. Stock stays until someone approves.'
                    : 'Condition and location updated.'
            ));
            redirect(url('inventory'));
        }
        if ($kind === 'purchase') {
            $instrument = normalize_payment_instrument(
                post_string('payment_mode', 30),
                post_string('upi_reference', 64),
                post_string('cheque_number', 30),
                post_string('cheque_date', 10),
                isset($_POST['cheque_cleared'])
            );
            if ($instrument['error'] !== null) {
                flash('error', $instrument['error']);
                redirect(url('inventory'));
            }
            $result = record_purchase([
                'item_id' => (int) ($_POST['item_id'] ?? 0),
                'name' => post_string('name', 150),
                'category' => one_of(post_string('category', 50), selection_values('inventory_categories'), 'Other'),
                'unit' => one_of(post_string('unit', 30), selection_values('units'), 'pcs'),
                'quantity' => (int) ($_POST['quantity'] ?? 0),
                'unit_cost' => (float) ($_POST['unit_cost'] ?? 0),
                'location' => post_string('location', 100) ?: null,
                'paid_to' => post_string('paid_to', 150) ?: null,
                'purchase_date' => post_string('purchase_date', 10),
                'payment_mode' => post_string('payment_mode', 30),
                'cheque_number' => $instrument['cheque_number'],
                'cheque_date' => $instrument['cheque_date'],
                'cheque_cleared' => $instrument['cheque_cleared'],
                'upi_reference' => $instrument['upi_reference'],
            ], $userId);
            flash($result['error'] !== null ? 'error' : 'success', $result['error'] ?? 'Purchase submitted for approval. Stock and the cash book change only after it is approved.');
            redirect(url('inventory'));
        }
        $name = post_string('name', 150);
        $category = one_of(post_string('category', 50), selection_values('inventory_categories'), 'Other');
        $qty = max(0, (int) ($_POST['quantity'] ?? 0));
        if ($name === '') {
            flash('error', 'Item name is required.');
            redirect(url('inventory'));
        }
        $condition = one_of(post_string('item_condition', 30), inventory_conditions(), 'Good');
        $existingId = (int) ($_POST['item_id'] ?? 0);
        if ($existingId > 0) {
            $existing = db_one('SELECT * FROM inventory_items WHERE id = ?', [$existingId]);
            if (
                $existing !== null
                && strcasecmp((string) $existing['name'], $name) === 0
                && (string) $existing['category'] === $category
            ) {
                $location = post_string('location', 100);
                $description = post_string('description', 2000);
                db_exec(
                    'UPDATE inventory_items
                     SET quantity = quantity + ?, unit_cost = ?, unit = ?, item_condition = ?, location = ?, description = ?
                     WHERE id = ?',
                    [
                        $qty,
                        max(0, (float) ($_POST['unit_cost'] ?? 0)),
                        one_of(post_string('unit', 30), selection_values('units'), (string) $existing['unit']),
                        $condition,
                        $location !== '' ? $location : ($existing['location'] ?? null),
                        $description !== '' ? $description : ($existing['description'] ?? null),
                        $existingId,
                    ]
                );
                if ($qty > 0) {
                    db_exec(
                        'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)',
                        [$existingId, 'Added', $qty, 'Added to existing stock', date('Y-m-d'), $userId]
                    );
                }
                flash('success', 'Stock added to the existing item.');
                redirect(url('inventory'));
            }
        }
        $itemId = db_exec(
            'INSERT INTO inventory_items (category, name, description, quantity, unit_cost, unit, item_condition, location, source, added_date, added_by, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $category,
                $name,
                post_string('description', 2000) ?: null,
                $qty,
                max(0, (float) ($_POST['unit_cost'] ?? 0)),
                one_of(post_string('unit', 30), selection_values('units'), 'pcs'),
                $condition,
                post_string('location', 100) ?: null,
                one_of(post_string('source', 30), selection_values('sources'), 'Purchased'),
                date('Y-m-d'),
                $userId,
                post_string('notes', 2000) ?: null,
            ]
        );
        if ($qty > 0) {
            db_exec(
                'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)',
                [$itemId, 'Added', $qty, 'Opening stock', date('Y-m-d'), $userId]
            );
        }
        flash('success', 'Inventory item added.');
        redirect(url('inventory'));
    }
    render('inventory', [
        'title' => t('nav.inventory'),
        'pageTitle' => t('page.inventory'),
        'active' => 'inventory',
        'items' => db_all('SELECT * FROM inventory_items ORDER BY category, name'),
        'categories' => selection_values('inventory_categories'),
        'conditions' => inventory_conditions(),
        'movements' => inventory_form_movements(),
        'writeOffLimit' => STOCK_WRITE_OFF_LIMIT,
        'today' => date('Y-m-d'),
        'history' => db_all(
            'SELECT m.*, i.name, i.unit FROM inventory_movements m
             JOIN inventory_items i ON i.id = m.item_id
             ORDER BY m.movement_date DESC, m.id DESC LIMIT 30'
        ),
        'pending' => db_all(
            "SELECT r.item_name, r.movement_type, r.quantity, r.movement_date, a.status
             FROM stock_requests r
             JOIN approvals a ON a.subject_type = 'stock' AND a.subject_id = r.id
             WHERE r.store_name = 'inventory' AND a.status IN ('Draft', 'Waiting', 'Sent back')
             ORDER BY r.id DESC"
        ),
    ]);
}

function action_food(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $action = post_string('action', 20);
        if ($action === 'new_item') {
            $name = post_string('name', 150);
            if ($name === '') {
                flash('error', 'Food item name is required.');
                redirect(url('food'));
            }
            $unit = one_of(post_string('unit', 30), selection_values('units'), 'kg');
            $added = (float) ($_POST['current_stock'] ?? 0);
            $threshold = (float) ($_POST['minimum_threshold'] ?? 0);
            $existing = db_one('SELECT id FROM food_items WHERE name = ?', [$name]);
            if ($existing !== null) {
                db_exec(
                    'UPDATE food_items
                     SET unit = ?, current_stock = current_stock + ?, minimum_threshold = ?, last_updated = ?
                     WHERE id = ?',
                    [$unit, $added, $threshold, date('Y-m-d H:i:s'), (int) $existing['id']]
                );
                flash('success', 'That food item is already on the list. The quantity was added to it.');
            } else {
                db_exec(
                    'INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)',
                    [$name, $unit, $added, $threshold]
                );
                flash('success', 'Food item added to stock list.');
            }
        } elseif ($action === 'add_stock' || $action === 'use_stock') {
            $result = record_stock_movement(
                'food',
                (int) ($_POST['food_item_id'] ?? 0),
                $action === 'add_stock' ? 'Added' : 'Used',
                (float) ($_POST['quantity'] ?? 0),
                post_string('purpose', 200),
                date('Y-m-d'),
                (int) $_SESSION['user_id']
            );
            if ($result['error'] !== null) {
                flash('error', $result['error']);
            } elseif ($action === 'add_stock') {
                flash('success', 'Stock added.');
            } elseif ($result['outcome'] === 'waiting') {
                flash('success', 'That usage is above the write-off limit and is waiting for approval. Stock is unchanged until then.');
            } else {
                flash('success', 'Stock usage logged.');
            }
        }
        redirect(url('food'));
    }
    render('food', [
        'title' => t('nav.food'),
        'pageTitle' => t('page.food'),
        'active' => 'food',
        'items' => db_all('SELECT * FROM food_items ORDER BY name'),
        'logs' => db_all(
            'SELECT l.*, f.name AS food_name, f.unit FROM food_usage_log l
             JOIN food_items f ON l.food_item_id = f.id
             ORDER BY l.txn_date DESC, l.id DESC LIMIT 30'
        ),
        'pending' => db_all(
            "SELECT r.item_name, r.movement_type, r.quantity, r.movement_date, a.status
             FROM stock_requests r
             JOIN approvals a ON a.subject_type = 'stock' AND a.subject_id = r.id
             WHERE r.store_name = 'food' AND a.status IN ('Draft', 'Waiting', 'Sent back')
             ORDER BY r.id DESC"
        ),
        'writeOffLimit' => STOCK_WRITE_OFF_LIMIT,
    ]);
}

function action_food_coupons(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $expiry = coupon_expires_at(isset($_POST['no_expiry']), (string) ($_POST['expires_at'] ?? ''));
        if ($expiry['error'] !== null) {
            flash('error', $expiry['error']);
            redirect(url('food/coupons'));
        }
        $result = create_coupon_batch(
            post_string('coupon_name', 100),
            (float) ($_POST['cost'] ?? 0),
            (int) ($_POST['quantity'] ?? 0),
            (int) $_SESSION['user_id'],
            $expiry['expires_at'],
            coupon_gift_from_post()
        );
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            redirect(url('food/coupons'));
        }
        flash('success', sprintf(
            'Batch submitted for approval — Sl No %d to %d, total %s. It is not counted, and it cannot be printed, until someone else approves it.',
            (int) $result['start'],
            (int) $result['end'],
            money($result['total'], 2)
        ));
        redirect(url('food/coupons'));
    }
    expire_due_coupons();
    $batches = db_all(
        "SELECT b.*, u.full_name AS created_by_name, a.status AS approval_status, a.prepared_by
         FROM food_coupon_batches b
         LEFT JOIN users u ON b.created_by = u.id
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         ORDER BY b.id DESC"
    );
    $totalValue = (float) db_value(
        "SELECT COALESCE(SUM(b.total_value),0) FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id AND a.status = 'Approved'"
    );
    $totalQty = (int) db_value(
        "SELECT COALESCE(SUM(b.quantity),0) FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id AND a.status = 'Approved'"
    );
    $couponIncome = (float) db_value(
        "SELECT COALESCE(SUM(d.amount),0)
         FROM food_coupons c
         JOIN donations d ON d.id = c.donation_id
         WHERE c.status = 'Redeemed'"
    );
    render('food_coupons', [
        'title' => t('page.coupons'),
        'pageTitle' => t('page.coupons'),
        'active' => 'food',
        'batches' => $batches,
        'couponCounts' => coupon_batch_counts(),
        'couponScans' => coupon_batch_scans(),
        'moneyModes' => money_payment_modes(),
        'totalCouponsValue' => $totalValue,
        'totalCouponQty' => $totalQty,
        'couponIncome' => $couponIncome,
        'role' => (string) ($_SESSION['role'] ?? ''),
    ]);
}

function action_coupon_scan(string $method): void
{
    login_required();
    $raw = $method === 'POST'
        ? (string) ($_POST['code'] ?? '')
        : (isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '');
    if (trim($raw) === '') {
        render('coupon_scan', [
            'title' => 'Coupon',
            'pageTitle' => 'Coupon',
            'active' => 'food',
            'preview' => coupon_scan_preview($raw),
            'saved' => false,
        ]);
        return;
    }
    try {
        $result = redeem_coupon($raw, (int) $_SESSION['user_id'], '', '');
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon scan: ' . $e->getMessage());
        render('coupon_scan', [
            'title' => 'Coupon',
            'pageTitle' => 'Coupon',
            'active' => 'food',
            'preview' => [
                'error' => 'The coupon could not be recorded.',
                'code' => null,
                'amount' => null,
                'purpose' => null,
                'status' => null,
            ],
            'saved' => false,
        ]);
        return;
    }
    if ($result['error'] !== null) {
        render('coupon_scan', [
            'title' => 'Coupon',
            'pageTitle' => 'Coupon',
            'active' => 'food',
            'preview' => [
                'error' => $result['error'],
                'code' => $result['code'],
                'amount' => null,
                'purpose' => null,
                'status' => $result['status'],
            ],
            'saved' => false,
        ]);
        return;
    }
    render('coupon_scan', [
        'title' => 'Coupon',
        'pageTitle' => 'Coupon',
        'active' => 'food',
        'preview' => [
            'error' => null,
            'code' => $result['code'],
            'amount' => $result['amount'],
            'purpose' => $result['purpose'],
            'donor_name' => $result['donor_name'],
            'payment_mode' => $result['payment_mode'],
            'status' => 'Redeemed',
        ],
        'saved' => true,
    ]);
}

function action_validate_coupon(): void
{
    login_required();
    try {
        $result = redeem_coupon(
            post_string('code', 40),
            (int) $_SESSION['user_id'],
            post_string('donor_name', 150),
            post_string('payment_mode', 30),
            post_string('upi_reference', 64),
            post_string('cheque_number', 30),
            post_string('cheque_date', 10),
            isset($_POST['cheque_cleared'])
        );
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon redeem: ' . $e->getMessage());
        flash('error', 'The coupon could not be recorded.');
        redirect(url('food/coupons'));
    }
    if ($result['error'] !== null) {
        flash('error', $result['error']);
    } else {
        flash('success', sprintf(
            'Recorded %s as a %s donation for %s. It is in the books.',
            (string) $result['code'],
            money((float) $result['amount'], 2),
            (string) $result['purpose']
        ));
    }
    redirect(url('food/coupons'));
}

function action_invalidate_coupon(): void
{
    login_required();
    try {
        $result = invalidate_coupon(post_string('code', 40));
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon invalidate: ' . $e->getMessage());
        flash('error', 'The coupon could not be invalidated.');
        redirect(url('food/coupons'));
    }
    if ($result['error'] !== null) {
        flash('error', $result['error']);
    } else {
        flash('success', (string) $result['code'] . ' is invalidated. No donation was added.');
    }
    redirect(url('food/coupons'));
}

function action_coupon_api(string $method, string $path): void
{
    if ($path === 'api/coupons/status' && $method === 'GET') {
        $actor = coupon_actor_from_bearer() ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        if ($actor === null) {
            coupon_json(['ok' => false, 'error' => 'Sign in, or send the coupon API token.'], 401);
        }
        $code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
        $result = coupon_status($code);
        if ($result['error'] !== null) {
            $status = $result['error'] === 'That coupon was not found.' ? 404 : 422;
            coupon_json(['ok' => false, 'error' => $result['error']], $status);
        }
        coupon_json(['ok' => true] + ($result['coupon'] ?? []));
    }
    if ($method !== 'POST' || ($path !== 'api/coupons/validate' && $path !== 'api/coupons/invalidate')) {
        coupon_json(['ok' => false, 'error' => 'That coupon API call was not found.'], 404);
    }
    $bearer = coupon_actor_from_bearer();
    if ($bearer === null && isset($_SESSION['user_id'])) {
        require_csrf();
        $bearer = (int) $_SESSION['user_id'];
    }
    if ($bearer === null) {
        coupon_json(['ok' => false, 'error' => 'Sign in, or send the coupon API token.'], 401);
    }
    $input = coupon_api_fields();
    if ($input['error'] !== null) {
        coupon_json(['ok' => false, 'error' => $input['error']], 422);
    }
    $fields = $input['fields'];
    try {
        if ($path === 'api/coupons/invalidate') {
            $result = invalidate_coupon(coupon_field($fields, 'code', 40));
            if ($result['error'] !== null) {
                coupon_json(['ok' => false, 'error' => $result['error'], 'code' => $result['code'], 'status' => $result['status']], 409);
            }
            coupon_json(['ok' => true, 'code' => $result['code'], 'status' => $result['status']]);
        }
        $result = redeem_coupon(
            coupon_field($fields, 'code', 40),
            $bearer,
            coupon_field($fields, 'donor_name', 150),
            coupon_field($fields, 'payment_mode', 30),
            coupon_field($fields, 'upi_reference', 64),
            coupon_field($fields, 'cheque_number', 30),
            coupon_field($fields, 'cheque_date', 10),
            !empty($fields['cheque_cleared'])
        );
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon api: ' . $e->getMessage());
        coupon_json(['ok' => false, 'error' => 'The coupon could not be recorded.'], 500);
    }
    if ($result['error'] !== null) {
        $status = $result['error'] === 'That coupon was not found.' ? 404 : 409;
        if (str_starts_with($result['error'], 'Enter ') || str_starts_with($result['error'], 'Choose ') || str_starts_with($result['error'], 'Send ')) {
            $status = 422;
        }
        coupon_json(['ok' => false, 'error' => $result['error'], 'code' => $result['code'], 'status' => $result['status']], $status);
    }
    coupon_json([
        'ok' => true,
        'code' => $result['code'],
        'status' => $result['status'],
        'amount' => $result['amount'],
        'purpose' => $result['purpose'],
        'donor_name' => $result['donor_name'],
        'payment_mode' => $result['payment_mode'],
        'donation_id' => $result['donation_id'],
    ]);
}

function coupon_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function action_update_coupons(int $batchId): void
{
    login_required();
    try {
        $expiry = coupon_expires_at(isset($_POST['no_expiry']), (string) ($_POST['expires_at'] ?? ''));
        if ($expiry['error'] !== null) {
            flash('error', $expiry['error']);
            redirect(url('food/coupons'));
        }
        $result = update_coupon_batch(
            $batchId,
            post_string('coupon_name', 100),
            (float) ($_POST['cost'] ?? 0),
            (int) ($_POST['quantity'] ?? 0),
            (int) $_SESSION['user_id'],
            $expiry['expires_at'],
            true,
            coupon_gift_from_post()
        );
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon edit: ' . $e->getMessage());
        flash('error', 'The coupon batch could not be saved.');
        redirect(url('food/coupons'));
    }
    if ($result['error'] !== null) {
        flash('error', $result['error']);
    } elseif (!empty($result['reapproval'])) {
        flash('success', 'Coupon batch updated and submitted for approval again. It is not counted until someone else approves it.');
    } elseif ($result['changed']) {
        flash('success', 'Coupon batch updated.');
    } else {
        flash('success', 'No change to save.');
    }
    redirect(url('food/coupons'));
}

function action_remove_coupons(int $batchId): void
{
    login_required();
    try {
        $error = remove_coupon_batch($batchId, (string) ($_SESSION['role'] ?? ''));
    } catch (Throwable $e) {
        error_log('[jt_blr] coupon remove: ' . $e->getMessage());
        flash('error', 'The coupon batch could not be removed.');
        redirect(url('food/coupons'));
    }
    flash($error !== null ? 'error' : 'success', $error ?? 'Coupon batch removed, including its unused coupons.');
    redirect(url('food/coupons'));
}

function action_print_coupons(int $batchId): void
{
    login_required();
    $batch = db_one(
        "SELECT b.*, a.status AS approval_status FROM food_coupon_batches b
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE b.id = ?",
        [$batchId]
    );
    if ($batch === null) {
        flash('error', 'Coupon batch not found.');
        redirect(url('food/coupons'));
    }
    if ((string) ($batch['approval_status'] ?? '') !== 'Approved') {
        flash('error', 'This batch can be printed after it is approved.');
        redirect(url('food/coupons'));
    }
    $path = generate_coupon_batch_pdf(
        $batchId,
        (string) $batch['coupon_name'],
        (float) $batch['cost'],
        (int) $batch['start_sl_no'],
        (int) $batch['quantity']
    );
    send_pdf($path, 'batch_' . $batchId . '.pdf');
}

function action_vastra(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $deity = one_of(post_string('deity_name', 100), selection_values('deities'), 'Jagannath');
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
                one_of(post_string('source', 30), selection_values('sources'), 'Purchased'),
                date('Y-m-d'),
                one_of(post_string('status', 30), selection_values('vastra_statuses'), 'In Store'),
                post_string('notes', 500) ?: null,
            ]
        );
        flash('success', 'Vastra item added.');
        redirect(url('vastra'));
    }
    render('vastra', [
        'title' => t('nav.vastra'),
        'pageTitle' => t('page.vastra'),
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
        'title' => t('page.donations'),
        'pageTitle' => t('page.donations'),
        'active' => 'donations',
        'donations' => db_all(
            "SELECT d.*, don.name AS donor_name, don.phone AS donor_phone,
                    (SELECT rc.reason FROM receipt_cancellations rc
                     JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id AND a.status = 'Approved'
                     WHERE rc.donation_id = d.id ORDER BY rc.id DESC LIMIT 1) AS cancel_reason,
                    (SELECT a.status FROM receipt_cancellations rc
                     JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id
                     WHERE rc.donation_id = d.id AND a.status IN ('Draft', 'Waiting', 'Sent back')
                     ORDER BY rc.id DESC LIMIT 1) AS cancel_status
             FROM donations d
             JOIN donors don ON d.donor_id = don.id ORDER BY d.donation_date DESC"
        ),
        'foodItems' => db_all('SELECT * FROM food_items ORDER BY name'),
        'pledges' => open_pledge_choices(),
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
    $type = one_of(post_string('donation_type', 30), array_column(selection_pairs('donation_types'), 'value'), 'Cash');
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $amount = $amountRaw === '' ? null : round((float) $amountRaw, 2);
    $donationDate = post_date('donation_date');
    $pledgeId = (int) ($_POST['pledge_id'] ?? 0);
    $pledge = null;
    if ($pledgeId > 0) {
        $pledge = db_one(
            'SELECT p.*, d.name AS donor_name, d.phone AS donor_phone
             FROM pledges p JOIN donors d ON d.id = p.donor_id WHERE p.id = ?',
            [$pledgeId]
        );
        if ($pledge === null) {
            flash('error', 'That pledge was not found.');
            return;
        }
        $pledgePhone = (string) ($pledge['donor_phone'] ?? '');
        if ($phone !== '' && $pledgePhone !== '' && $phone !== $pledgePhone) {
            flash('error', 'This pledge belongs to ' . $pledge['donor_name'] . '.');
            return;
        }
    }
    $paymentMode = one_of(
        post_string('payment_mode', 30),
        payment_mode_names(),
        $type === 'Cash' ? 'Cash' : 'In-Kind'
    );
    $instrument = normalize_payment_instrument(
        $paymentMode,
        post_string('upi_reference', 64),
        post_string('cheque_number', 30),
        post_string('cheque_date', 10),
        isset($_POST['cheque_cleared'])
    );
    if ($instrument['error'] !== null) {
        flash('error', $instrument['error']);
        return;
    }
    if ($pledge !== null) {
        $pledgeError = pledge_receipt_error((float) ($amount ?? 0), $donationDate, $paymentMode);
        if ($pledgeError !== null) {
            flash('error', $pledgeError);
            return;
        }
    }
    $email = post_string('donor_email', 120);
    $address = post_string('donor_address', 500);
    $pan = post_string('pan_number', 20);
    $postedDonorId = (int) ($_POST['donor_id'] ?? 0);
    $donor = null;
    if ($pledge === null && $postedDonorId > 0) {
        $donor = db_one('SELECT * FROM donors WHERE id = ?', [$postedDonorId]);
    }
    if ($donor === null && $pledge === null && $phone !== '') {
        $donor = db_one('SELECT * FROM donors WHERE phone = ?', [$phone]);
    }
    if ($donor !== null) {
        $saved = save_devotee((int) $donor['id'], [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'pan' => $pan,
        ]);
        if ($saved['error'] !== null) {
            flash('error', $saved['error']);
            return;
        }
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($pledge !== null) {
            $donorId = (int) $pledge['donor_id'];
        } elseif ($donor !== null) {
            $donorId = (int) $donor['id'];
        } else {
            $donorId = db_exec(
                'INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)',
                [
                    $name,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $pan !== '' ? $pan : null,
                ]
            );
        }
        $donationId = db_exec(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, cheque_number, cheque_date, cheque_cleared, upi_reference, pledge_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $donorId,
                $type,
                $amount,
                $pledge !== null ? (string) $pledge['purpose'] : one_of(post_string('purpose', 80), selection_values('purposes'), 'General'),
                $donationDate,
                $paymentMode,
                $instrument['cheque_number'],
                $instrument['cheque_date'],
                $instrument['cheque_cleared'],
                $instrument['upi_reference'],
                $pledge !== null ? (int) $pledge['id'] : null,
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
            $deity = one_of(post_string('vastra_deity', 50), selection_values('deities'), '');
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
            $inventoryCategory = one_of(post_string('inventory_category', 50), selection_values('inventory_categories'), 'Other');
            $existingInventoryId = (int) ($_POST['inventory_item_id'] ?? 0);
            $existingInventory = $existingInventoryId > 0
                ? db_one('SELECT id, name, category FROM inventory_items WHERE id = ?', [$existingInventoryId])
                : null;
            if (
                $itemName !== ''
                && $existingInventory !== null
                && strcasecmp((string) $existingInventory['name'], $itemName) === 0
                && (string) $existingInventory['category'] === $inventoryCategory
            ) {
                $giftQty = max(1, (int) ($_POST['inventory_quantity'] ?? 1));
                db_exec('UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?', [$giftQty, $existingInventoryId]);
                db_exec(
                    'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)',
                    [$existingInventoryId, 'Added', $giftQty, 'Donated by ' . $name, date('Y-m-d'), (int) $_SESSION['user_id']]
                );
                db_exec('UPDATE donations SET linked_inventory_id = ? WHERE id = ?', [$existingInventoryId, $donationId]);
            } elseif ($itemName !== '') {
                $inventoryId = db_exec(
                    'INSERT INTO inventory_items (category, name, quantity, unit, source, donation_id, added_date, added_by, notes)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        $inventoryCategory,
                        $itemName,
                        max(1, (int) ($_POST['inventory_quantity'] ?? 1)),
                        one_of(post_string('inventory_unit', 30), selection_values('units'), 'pcs'),
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

function action_donors(): void
{
    login_required();
    $range = default_ledger_range();
    $from = valid_book_date((string) ($_GET['from'] ?? '')) ?? $range['from'];
    $to = valid_book_date((string) ($_GET['to'] ?? '')) ?? $range['to'];
    $error = cash_book_range_error($from, $to);
    if ($error !== null) {
        flash('error', $error);
        $from = $range['from'];
        $to = $range['to'];
    }
    render('donors', [
        'title' => t('nav.donors'),
        'pageTitle' => t('page.donor_ledger'),
        'active' => 'donors',
        'donors' => load_donor_list($from, $to, (string) ($_GET['q'] ?? '')),
        'from' => $from,
        'to' => $to,
        'query' => (string) ($_GET['q'] ?? ''),
        'financialYear' => financial_year_label($from),
        'countryCode' => load_messaging_settings()['whatsapp_country_code'],
    ]);
}

function action_donors_bulk_email(): void
{
    login_required();
    $message = post_string('message', 1000);
    $ids = posted_id_list('donor_ids');
    $error = bulk_compose_error($message, count($ids));
    if ($error !== null) {
        flash('error', $error);
        redirect(url('donors'));
    }
    $settings = load_messaging_settings();
    if (!smtp_is_ready($settings)) {
        flash('error', 'Outgoing mail is not configured.');
        redirect(url('donors'));
    }
    $sent = 0;
    $skipped = 0;
    $failed = 0;
    $subject = 'Message from ' . app_display_name();
    foreach ($ids as $donorId) {
        $donor = db_one('SELECT name, email FROM donors WHERE id = ?', [$donorId]);
        $email = trim((string) ($donor['email'] ?? ''));
        if ($donor === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $skipped++;
            continue;
        }
        $sendError = send_smtp_message($settings, $email, $subject, $message);
        if ($sendError !== null) {
            notify_log('EMAIL', $email, 'Not sent. ' . $subject . ' ' . $sendError);
            $failed++;
            continue;
        }
        notify_log('EMAIL', $email, 'Sent. ' . $subject);
        $sent++;
    }
    [$category, $text] = bulk_result_flash($sent, $skipped, $failed);
    flash($category, $text);
    redirect(url('donors'));
}

function action_donor(int $donorId): void
{
    login_required();
    $range = default_ledger_range();
    $from = valid_book_date((string) ($_GET['from'] ?? '')) ?? $range['from'];
    $to = valid_book_date((string) ($_GET['to'] ?? '')) ?? $range['to'];
    $error = cash_book_range_error($from, $to);
    if ($error !== null) {
        flash('error', $error);
        $from = $range['from'];
        $to = $range['to'];
    }
    try {
        $statement = load_donor_statement($donorId, $from, $to);
    } catch (RuntimeException) {
        flash('error', 'That devotee was not found.');
        redirect(url('donors'));
    }
    $gifts = db_one('SELECT COUNT(*) AS n FROM donations WHERE donor_id = ?', [$donorId]);
    $pledges = db_one('SELECT COUNT(*) AS n FROM pledges WHERE donor_id = ?', [$donorId]);
    render('donor', [
        'title' => $statement['name'],
        'pageTitle' => $statement['name'],
        'active' => 'donors',
        'statement' => $statement,
        'today' => date('Y-m-d'),
        'deleteReason' => devotee_delete_block_reason((int) ($gifts['n'] ?? 0), (int) ($pledges['n'] ?? 0)),
    ]);
}

function action_devotee_new(string $method): void
{
    login_required();
    $values = devotee_fields(['name' => '', 'phone' => '', 'email' => '', 'address' => '', 'pan' => '']);
    if ($method === 'POST') {
        $values = devotee_fields([
            'name' => post_string('name', 150),
            'phone' => post_string('phone', 20),
            'email' => post_string('email', 120),
            'address' => post_string('address', 500),
            'pan' => post_string('pan', 20),
        ]);
        $saved = save_devotee(null, $values);
        if ($saved['error'] === null) {
            flash('success', 'Devotee added.');
            redirect(url('donors/' . $saved['id']));
        }
        flash('error', $saved['error']);
    } elseif ($method !== 'GET') {
        http_response_code(405);
        echo 'Method not allowed.';
        return;
    }
    render('devotee_form', [
        'title' => t('page.add_devotee'),
        'pageTitle' => t('page.add_devotee'),
        'active' => 'donors',
        'values' => $values,
    ]);
}

function action_devotee_save(int $donorId): void
{
    login_required();
    $saved = save_devotee($donorId, [
        'name' => post_string('name', 150),
        'phone' => post_string('phone', 20),
        'email' => post_string('email', 120),
        'address' => post_string('address', 500),
        'pan' => post_string('pan', 20),
    ]);
    if ($saved['error'] !== null) {
        flash('error', $saved['error']);
        redirect(url('donors/' . $donorId));
    }
    flash('success', 'Devotee details saved.');
    redirect(url('donors/' . $donorId));
}

function action_devotee_delete(int $donorId): void
{
    login_required();
    $error = delete_devotee($donorId);
    if ($error !== null) {
        flash('error', $error);
        redirect(url('donors/' . $donorId));
    }
    flash('success', 'Devotee removed.');
    redirect(url('donors'));
}

function action_save_pledge(int $donorId): void
{
    login_required();
    if (db_one('SELECT id FROM donors WHERE id = ?', [$donorId]) === null) {
        flash('error', 'That devotee was not found.');
        redirect(url('donors'));
    }
    $amount = round((float) ($_POST['pledged_amount'] ?? 0), 2);
    $date = post_string('pledge_date', 10);
    $purpose = post_string('purpose', 200);
    $error = pledge_request_error($amount, $date, $purpose);
    if ($error !== null) {
        flash('error', $error);
        redirect(url('donors/' . $donorId));
    }
    db_exec(
        'INSERT INTO pledges (donor_id, purpose, pledged_amount, pledge_date, note, created_by) VALUES (?,?,?,?,?,?)',
        [$donorId, trim($purpose), $amount, $date, post_string('note', 255) ?: null, (int) $_SESSION['user_id']]
    );
    flash('success', 'Pledge recorded. It is a promise, so it is not in the cash book until money is received.');
    redirect(url('donors/' . $donorId));
}

function action_receive_pledge(int $donorId): void
{
    login_required();
    $pledge = db_one('SELECT * FROM pledges WHERE id = ? AND donor_id = ?', [(int) ($_POST['pledge_id'] ?? 0), $donorId]);
    if ($pledge === null) {
        flash('error', 'That pledge was not found.');
        redirect(url('donors/' . $donorId));
    }
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $date = post_string('donation_date', 10);
    $paymentMode = one_of(post_string('payment_mode', 30), money_payment_modes(), 'Cash');
    $error = pledge_receipt_error($amount, $date, $paymentMode);
    $instrument = normalize_payment_instrument(
        $paymentMode,
        post_string('upi_reference', 64),
        post_string('cheque_number', 30),
        post_string('cheque_date', 10),
        isset($_POST['cheque_cleared'])
    );
    if ($error === null) {
        $error = $instrument['error'];
    }
    if ($error !== null) {
        flash('error', $error);
        redirect(url('donors/' . $donorId));
    }
    db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, cheque_number, cheque_date, cheque_cleared, upi_reference, pledge_id, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $donorId,
            'Cash',
            $amount,
            (string) $pledge['purpose'],
            $date,
            $paymentMode,
            $instrument['cheque_number'],
            $instrument['cheque_date'],
            $instrument['cheque_cleared'],
            $instrument['upi_reference'],
            (int) $pledge['id'],
            (int) $_SESSION['user_id'],
        ]
    );
    flash('success', 'Amount received against the pledge. It is now in the cash book.');
    redirect(url('donors/' . $donorId));
}

function action_generate_receipt(int $donationId): void
{
    login_required();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $donation = db_one('SELECT * FROM donations WHERE id = ? FOR UPDATE', [$donationId]);
        if ($donation === null) {
            $pdo->rollBack();
            flash('error', 'Donation not found.');
            redirect(url('donations'));
        }
        $donor = db_one('SELECT * FROM donors WHERE id = ?', [(int) $donation['donor_id']]);
        if ($donor === null) {
            $pdo->rollBack();
            flash('error', 'Donor not found.');
            redirect(url('donations'));
        }
        $existing = trim((string) ($donation['receipt_number'] ?? ''));
        $receiptNumber = $existing !== '' ? $existing : next_receipt_number($pdo);
        $donation['receipt_share_token'] = receipt_ensure_share_token($donationId);
        generate_receipt_pdf($donation, $donor, (string) $receiptNumber);
        if ($existing === '') {
            $marked = db()->prepare(
                "UPDATE donations SET receipt_number = ?, receipt_generated = 1
                 WHERE id = ? AND (receipt_number IS NULL OR receipt_number = '')"
            );
            $marked->execute([$receiptNumber, $donationId]);
            if ($marked->rowCount() !== 1) {
                throw new RuntimeException('Receipt number was already saved.');
            }
        } else {
            db_exec('UPDATE donations SET receipt_generated = 1 WHERE id = ?', [$donationId]);
        }
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

function action_open_receipt(string $token): void
{
    $row = receipt_public_row($token);
    if ($row === null) {
        http_response_code(404);
        render('receipt_public', [
            'title' => 'Gift',
            'gift' => null,
        ], false);
        return;
    }
    render('receipt_public', [
        'title' => 'Gift',
        'gift' => $row,
    ], false);
}

function action_serve_receipt(string $filename): void
{
    login_required();
    $filename = basename($filename);
    $receiptNumber = str_ends_with($filename, '.pdf') ? substr($filename, 0, -4) : '';
    if (!receipt_number_is_valid($receiptNumber) || $filename !== $receiptNumber . '.pdf') {
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
        'title' => t('page.receipts'),
        'pageTitle' => t('page.receipts'),
        'active' => 'receipts',
        'receipts' => $rows,
        'onDisk' => $onDisk,
        'isAdmin' => ($_SESSION['role'] ?? '') === 'Admin',
        'countryCode' => load_messaging_settings()['whatsapp_country_code'],
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

function action_receipts_cancel(): void
{
    login_required();
    $reason = post_string('reason', 500);
    $rows = selected_receipt_rows();
    if ($rows === []) {
        flash('error', 'Select at least one receipt to cancel.');
        redirect(url('receipts'));
    }
    $submitted = 0;
    $skipped = 0;
    $userId = (int) $_SESSION['user_id'];
    foreach ($rows as $row) {
        $donationId = (int) $row['donation_id'];
        $error = receipt_cancel_request_error(
            true,
            (int) ($row['receipt_cancelled'] ?? 0) === 1,
            receipt_cancel_is_open($donationId),
            $reason
        );
        if ($error !== null) {
            $skipped++;
            continue;
        }
        $cancelId = db_exec(
            'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
            [$donationId, trim($reason), $userId]
        );
        $amount = round(abs((float) ($row['amount'] ?? 0)), 2);
        record_approval('receipt', $cancelId, 'Waiting', $amount, $userId);
        $submitted++;
    }
    if ($submitted === 0) {
        flash('error', $skipped > 0 ? 'Those receipts are already cancelled or already waiting.' : 'Write a short reason for the cancellation.');
        redirect(url('receipts'));
    }
    $message = $submitted . ' cancellation' . ($submitted === 1 ? '' : 's') . ' submitted for approval. The receipt number stays on the donation.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' skipped.';
    }
    flash('success', $message);
    redirect(url('receipts'));
}

function action_receipt_email(int $donationId): void
{
    login_required();
    $rows = receipt_rows($donationId);
    $row = $rows[0] ?? null;
    if ($row === null) {
        flash('error', 'Receipt not found.');
        redirect(url('receipts'));
    }
    $email = trim((string) ($row['donor_email'] ?? ''));
    $number = (string) $row['receipt_number'];
    $ready = receipt_file_exists($number);
    $settings = load_messaging_settings();
    $blocked = receipt_email_block_reason($email, $ready, smtp_is_ready($settings));
    if ($blocked !== null) {
        flash('error', $blocked);
        redirect(url('receipts'));
    }
    $pdf = file_get_contents(receipt_path($number));
    if ($pdf === false) {
        flash('error', 'The receipt PDF is not ready to send.');
        redirect(url('receipts'));
    }
    $subject = 'Receipt ' . $number;
    $error = send_smtp_message($settings, $email, $subject, receipt_email_body($row, receipt_public_url((string) ($row['receipt_share_token'] ?? ''))), [
        'filename' => $number . '.pdf',
        'content' => $pdf,
        'mime' => 'application/pdf',
    ]);
    if ($error !== null) {
        notify_log('EMAIL', $email, 'Not sent. ' . $subject . ' ' . $error);
        flash('error', 'The receipt was not emailed: ' . $error);
        redirect(url('receipts'));
    }
    notify_log('EMAIL', $email, 'Sent. ' . $subject);
    flash('success', 'Receipt ' . $number . ' emailed to ' . $email . '.');
    redirect(url('receipts'));
}

function action_receipts_bulk_email(): void
{
    login_required();
    $message = post_string('message', 1000);
    $ids = posted_id_list('donation_ids');
    $error = bulk_compose_error($message, count($ids));
    if ($error !== null) {
        flash('error', $error);
        redirect(url('receipts'));
    }
    $settings = load_messaging_settings();
    if (!smtp_is_ready($settings)) {
        flash('error', 'Outgoing mail is not configured.');
        redirect(url('receipts'));
    }
    $sent = 0;
    $skipped = 0;
    $failed = 0;
    foreach ($ids as $donationId) {
        $rows = receipt_rows($donationId);
        $row = $rows[0] ?? null;
        $email = trim((string) ($row['donor_email'] ?? ''));
        $number = (string) ($row['receipt_number'] ?? '');
        $ready = $row !== null && receipt_file_exists($number);
        if ($row === null || receipt_email_block_reason($email, $ready, true) !== null) {
            $skipped++;
            continue;
        }
        $pdf = file_get_contents(receipt_path($number));
        if ($pdf === false) {
            $skipped++;
            continue;
        }
        $subject = 'Receipt ' . $number;
        $link = receipt_public_url((string) ($row['receipt_share_token'] ?? ''));
        $sendError = send_smtp_message($settings, $email, $subject, receipt_bulk_email_body($message, $link), [
            'filename' => $number . '.pdf',
            'content' => $pdf,
            'mime' => 'application/pdf',
        ]);
        if ($sendError !== null) {
            notify_log('EMAIL', $email, 'Not sent. ' . $subject . ' ' . $sendError);
            $failed++;
            continue;
        }
        notify_log('EMAIL', $email, 'Sent. ' . $subject);
        $sent++;
    }
    [$category, $text] = bulk_result_flash($sent, $skipped, $failed);
    flash($category, $text);
    redirect(url('receipts'));
}

/** @return list<int> */
function posted_id_list(string $key): array
{
    $raw = $_POST[$key] ?? [];
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
    return array_values($ids);
}

/** @return list<array<string, mixed>> */
function receipt_rows(?int $donationId = null): array
{
    $params = [];
    $one = '';
    if ($donationId !== null) {
        $one = ' AND d.id = ?';
        $params[] = $donationId;
    }
    return db_all(
        "SELECT d.id AS donation_id, d.receipt_number, d.amount, d.donation_date, d.donation_type,
                d.purpose, d.payment_mode, d.receipt_cancelled, d.receipt_share_token,
                don.name AS donor_name, don.phone AS donor_phone, don.email AS donor_email,
                r.generated_date, u.full_name AS generated_by_name,
                (SELECT rc.reason FROM receipt_cancellations rc
                 JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id AND a.status = 'Approved'
                 WHERE rc.donation_id = d.id ORDER BY rc.id DESC LIMIT 1) AS cancel_reason,
                (SELECT a.status FROM receipt_cancellations rc
                 JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id
                 WHERE rc.donation_id = d.id AND a.status IN ('Draft', 'Waiting', 'Sent back')
                 ORDER BY rc.id DESC LIMIT 1) AS cancel_status
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         LEFT JOIN receipts r ON r.donation_id = d.id
         LEFT JOIN users u ON r.generated_by = u.id
         WHERE d.receipt_generated = 1
           AND d.receipt_number IS NOT NULL
           AND d.receipt_number <> ''
           {$one}
         ORDER BY d.donation_date DESC, d.id DESC",
        $params
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
        "SELECT d.id AS donation_id, d.receipt_number, d.amount, d.receipt_cancelled
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
    return receipt_number_is_valid($receiptNumber) && is_file(receipt_path($receiptNumber));
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

function action_cash_book(): void
{
    login_required();
    $range = book_range_from_request();
    if ($range['error'] !== null) {
        flash('error', $range['error']);
        $fallback = default_book_range();
        $range['from'] = $fallback['from'];
        $range['to'] = $fallback['to'];
    }
    $opening = load_opening_balance(financial_year_label($range['from']));
    $book = build_cash_book(
        load_book_movements($range['from'], $range['to']),
        $range['from'],
        $range['to'],
        $opening['cash'],
        $opening['bank']
    );
    render('cash_book', [
        'title' => t('page.cash_book'),
        'pageTitle' => t('page.cash_book'),
        'active' => 'cash-book',
        'book' => $book,
        'yearOpening' => $opening,
        'isAdmin' => ($_SESSION['role'] ?? '') === 'Admin',
        'canSetOpening' => in_array((string) ($_SESSION['role'] ?? ''), ['Admin', 'Treasurer'], true),
        'waitingCount' => (int) db_value("SELECT COUNT(*) FROM approvals WHERE status = 'Waiting'"),
        'carry' => carry_forward_preview($book['financial_year'], (string) ($_SESSION['role'] ?? '')),
    ]);
}

function action_corrections(string $method): void
{
    login_required();
    if ($method === 'POST') {
        action_save_correction();
        return;
    }
    render('corrections', [
        'title' => t('page.corrections'),
        'pageTitle' => t('page.corrections'),
        'active' => 'corrections',
        'targets' => correction_targets(),
        'rows' => correction_history(),
        'today' => date('Y-m-d'),
    ]);
}

function action_save_correction(): void
{
    login_required();
    $target = post_string('target', 40);
    if (preg_match('/^(donation|expense):(\d+)$/', $target, $match) !== 1) {
        flash('error', 'Choose the line to correct.');
        redirect(url('corrections'));
    }
    $type = $match[1];
    $subjectId = (int) $match[2];
    $posted = correction_posted($type, $subjectId);
    if ($posted === null) {
        flash('error', 'That line is not in the books yet.');
        redirect(url('corrections'));
    }
    $kind = post_string('kind', 20);
    $current = corrected_book_amount($type, $subjectId, $posted['amount']);
    $corrected = $kind === 'void' ? 0.0 : round((float) ($_POST['corrected_amount'] ?? 0), 2);
    $reason = post_string('reason', 500);
    $date = post_string('entry_date', 10);
    $error = correction_request_error(
        $kind,
        $current,
        $corrected,
        $reason,
        $date,
        book_account($posted['payment_mode']),
        correction_is_open($type, $subjectId)
    );
    if ($error !== null) {
        flash('error', $error);
        redirect(url('corrections'));
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $correctionId = db_exec(
            'INSERT INTO corrections (subject_type, subject_id, original_amount, corrected_amount, reason, entry_date, prepared_by)
             VALUES (?,?,?,?,?,?,?)',
            [$type, $subjectId, $current, $corrected, trim($reason), $date, (int) $_SESSION['user_id']]
        );
        record_approval('correction', $correctionId, 'Waiting', abs($corrected - $current), (int) $_SESSION['user_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] correction: ' . $e->getMessage());
        flash('error', 'The correction could not be saved.');
        redirect(url('corrections'));
    }
    flash('success', 'Correction submitted for approval. The original line stays until it is approved.');
    redirect(url('corrections'));
}

function action_approvals(): void
{
    login_required();
    render('approvals', [
        'title' => t('page.approvals'),
        'pageTitle' => t('page.approvals'),
        'active' => 'approvals',
        'rows' => db_all(
            "SELECT a.*, p.full_name AS prepared_name, d.full_name AS decided_name,
                    e.voucher_number, e.category, e.expense_date, e.description, e.paid_to,
                    c.direction, c.entry_date AS contra_date, c.note AS contra_note,
                    o.financial_year, o.pending_cash, o.pending_bank, o.cash_amount, o.bank_amount,
                    cor.subject_type AS correction_target, cor.original_amount, cor.corrected_amount, cor.reason AS correction_reason,
                    rd.receipt_number AS cancel_receipt, rn.name AS cancel_donor, rc.reason AS cancel_reason,
                    pur.item_name AS purchase_name, pur.quantity AS purchase_qty,
                    sr.item_name AS stock_name, sr.movement_type AS stock_movement, sr.quantity AS stock_qty,
                    cb.coupon_name, cb.quantity AS coupon_qty, cb.cost AS coupon_cost
             FROM approvals a
             JOIN users p ON p.id = a.prepared_by
             LEFT JOIN users d ON d.id = a.decided_by
             LEFT JOIN expenses e ON a.subject_type = 'expense' AND e.id = a.subject_id
             LEFT JOIN contra_entries c ON a.subject_type = 'contra' AND c.id = a.subject_id
             LEFT JOIN opening_balances o ON a.subject_type = 'opening' AND o.id = a.subject_id
             LEFT JOIN corrections cor ON a.subject_type = 'correction' AND cor.id = a.subject_id
             LEFT JOIN receipt_cancellations rc ON a.subject_type = 'receipt' AND rc.id = a.subject_id
             LEFT JOIN donations rd ON rc.donation_id = rd.id
             LEFT JOIN donors rn ON rd.donor_id = rn.id
             LEFT JOIN purchases pur ON a.subject_type = 'purchase' AND pur.id = a.subject_id
             LEFT JOIN stock_requests sr ON a.subject_type = 'stock' AND sr.id = a.subject_id
             LEFT JOIN food_coupon_batches cb ON a.subject_type = 'coupon' AND cb.id = a.subject_id
             WHERE a.status IN ('Draft', 'Waiting', 'Sent back')
             ORDER BY FIELD(a.status, 'Waiting', 'Sent back', 'Draft'), a.updated_at DESC"
        ),
    ]);
}

function action_decide_approval(int $id): void
{
    login_required();
    $row = db_one('SELECT * FROM approvals WHERE id = ?', [$id]);
    if ($row === null) {
        flash('error', 'That approval was not found.');
        redirect(url('approvals'));
    }
    $user = current_user();
    $decision = post_string('decision', 20);
    $note = post_string('decision_note', 500);
    $error = approval_error(
        (string) ($user['role'] ?? ''),
        (float) $row['amount'],
        (int) $row['prepared_by'],
        (int) ($user['id'] ?? 0),
        $decision,
        (string) $row['status'],
        $note
    );
    if ($error !== null) {
        flash('error', $error);
        redirect(url('approvals'));
    }
    $next = approval_next_status($decision);
    $pdo = db();
    $couponBatch = null;
    $pdo->beginTransaction();
    try {
        $deciding = in_array($decision, ['approve', 'send_back', 'reject'], true);
        $claimed = approval_claim_decision(
            $id,
            (string) $row['status'],
            $next,
            $deciding ? (int) $user['id'] : null,
            $deciding && $note !== '' ? $note : null
        );
        if (!$claimed) {
            $pdo->rollBack();
            flash('error', 'This was already decided. Refresh the page to see the latest status.');
            redirect(url('approvals'));
        }
        if ($row['subject_type'] === 'receipt' && $decision === 'approve') {
            apply_receipt_cancellation((int) $row['subject_id']);
        }
        if ($row['subject_type'] === 'opening' && $decision === 'approve') {
            db_exec(
                'UPDATE opening_balances
                 SET cash_amount = COALESCE(pending_cash, cash_amount),
                     bank_amount = COALESCE(pending_bank, bank_amount),
                     note = COALESCE(pending_note, note),
                     pending_cash = NULL, pending_bank = NULL, pending_note = NULL
                 WHERE id = ?',
                [(int) $row['subject_id']]
            );
        }
        if ($row['subject_type'] === 'purchase' && $decision === 'approve') {
            apply_approved_purchase((int) $row['subject_id']);
        }
        if ($row['subject_type'] === 'stock' && $decision === 'approve') {
            apply_approved_stock((int) $row['subject_id']);
        }
        if ($row['subject_type'] === 'coupon' && $decision === 'approve') {
            $couponBatch = db_one('SELECT * FROM food_coupon_batches WHERE id = ?', [(int) $row['subject_id']]);
            if ($couponBatch === null) {
                throw new RuntimeException('Coupon batch is missing.');
            }
        }
        if ($row['subject_type'] === 'opening' && $decision === 'reject') {
            db_exec(
                'UPDATE opening_balances SET pending_cash = NULL, pending_bank = NULL, pending_note = NULL WHERE id = ?',
                [(int) $row['subject_id']]
            );
        }
        $pdo->commit();
    } catch (StockApplyException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e->getMessage());
        redirect(url('approvals'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[jt_blr] approval: ' . $e->getMessage());
        flash('error', 'The decision could not be saved.');
        redirect(url('approvals'));
    }
    if (is_array($couponBatch)) {
        try {
            generate_coupon_batch_pdf(
                (int) $couponBatch['id'],
                (string) $couponBatch['coupon_name'],
                (float) $couponBatch['cost'],
                (int) $couponBatch['start_sl_no'],
                (int) $couponBatch['quantity']
            );
        } catch (Throwable $e) {
            error_log('[jt_blr] coupon sheet: ' . $e->getMessage());
            flash('error', 'The batch is approved. Print it again to build the coupon sheet.');
            redirect(url('approvals'));
        }
    }
    flash('success', 'Marked ' . $next . '.');
    redirect(url('approvals'));
}

function action_day_book(): void
{
    login_required();
    $range = book_range_from_request();
    if ($range['error'] !== null) {
        flash('error', $range['error']);
        $fallback = default_book_range();
        $range['from'] = $fallback['from'];
        $range['to'] = $fallback['to'];
    }
    $opening = load_opening_balance(financial_year_label($range['from']));
    $book = build_cash_book(
        load_book_movements($range['from'], $range['to']),
        $range['from'],
        $range['to'],
        $opening['cash'],
        $opening['bank']
    );
    render('day_book', [
        'title' => t('page.day_book'),
        'pageTitle' => t('page.day_book'),
        'active' => 'day-book',
        'book' => $book,
        'rows' => build_day_book($book['lines']),
    ]);
}

function action_ledger(): void
{
    login_required();
    $range = ledger_range_from_request();
    if ($range['error'] !== null) {
        flash('error', $range['error']);
        $fallback = default_ledger_range();
        $range['from'] = $fallback['from'];
        $range['to'] = $fallback['to'];
    }
    $heads = build_ledgers(load_ledger_lines($range['from'], $range['to']), $range['from'], $range['to']);
    $selectedName = trim((string) ($_GET['head'] ?? ''));
    if (mb_strlen($selectedName) > 200) {
        $selectedName = mb_substr($selectedName, 0, 200);
    }
    $selected = null;
    foreach ($heads as $head) {
        if ($head['head'] === $selectedName) {
            $selected = $head;
            break;
        }
    }
    render('ledger', [
        'title' => t('page.ledger'),
        'pageTitle' => t('page.ledger'),
        'active' => 'ledger',
        'from' => $range['from'],
        'to' => $range['to'],
        'financialYear' => financial_year_label($range['from']),
        'heads' => $heads,
        'selected' => $selected,
    ]);
}

/** @return array{from: string, to: string, error: ?string} */
function ledger_range_from_request(): array
{
    $fallback = default_ledger_range();
    $from = valid_book_date((string) ($_GET['from'] ?? '')) ?? $fallback['from'];
    $to = valid_book_date((string) ($_GET['to'] ?? '')) ?? $fallback['to'];
    return ['from' => $from, 'to' => $to, 'error' => cash_book_range_error($from, $to)];
}

function action_save_opening(): void
{
    login_required();
    $role = (string) ($_SESSION['role'] ?? '');
    $back = url('cash-book', ['from' => post_string('from', 10), 'to' => post_string('to', 10)]);
    if ($role !== 'Admin' && $role !== 'Treasurer') {
        flash('error', 'Only a Treasurer or Admin can set the opening balance.');
        redirect($back);
    }
    $year = post_string('financial_year', 9);
    $cash = round((float) ($_POST['cash_amount'] ?? 0), 2);
    $bank = round((float) ($_POST['bank_amount'] ?? 0), 2);
    $error = validate_opening_amounts($cash, $bank);
    if ($error === null) {
        try {
            financial_year_start($year);
        } catch (InvalidArgumentException) {
            $error = 'Choose a valid financial year.';
        }
    }
    if ($error !== null) {
        flash('error', $error);
        redirect($back);
    }
    try {
        submit_opening_balance($year, $cash, $bank, post_string('note', 255), (int) $_SESSION['user_id']);
    } catch (Throwable $e) {
        error_log('[jt_blr] opening: ' . $e->getMessage());
        flash('error', 'The opening balance could not be saved.');
        redirect($back);
    }
    flash('success', 'Opening balance submitted for approval. The books keep the last approved figures until then.');
    redirect($back);
}

function action_carry_opening(): void
{
    login_required();
    $role = (string) ($_SESSION['role'] ?? '');
    $back = url('cash-book', ['from' => post_string('from', 10), 'to' => post_string('to', 10)]);
    if ($role !== 'Admin' && $role !== 'Treasurer') {
        flash('error', 'Only a Treasurer or Admin can carry the closing balance forward.');
        redirect($back);
    }
    try {
        $result = submit_carried_opening(post_string('financial_year', 9), (int) $_SESSION['user_id']);
    } catch (Throwable $e) {
        error_log('[jt_blr] carry: ' . $e->getMessage());
        flash('error', 'The closing balance could not be carried forward.');
        redirect($back);
    }
    if ($result['error'] !== null || $result['next_year'] === null) {
        flash('error', $result['error'] ?? 'The closing balance could not be carried forward.');
        redirect($back);
    }
    $start = financial_year_start($result['next_year']);
    $end = financial_year_end($result['next_year']);
    $today = date('Y-m-d');
    $to = ($today >= $start && $today <= $end) ? $today : $start;
    flash(
        'success',
        'Closing cash ' . money($result['cash'], 2) . ' and bank ' . money($result['bank'], 2)
        . ' submitted as the opening for ' . $result['next_year']
        . '. The books keep the last approved figures until someone else approves it.'
    );
    redirect(url('cash-book', ['from' => $start, 'to' => $to]));
}

function action_save_contra(): void
{
    login_required();
    $direction = post_string('direction', 20);
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $date = post_string('entry_date', 10);
    $error = validate_contra($direction, $amount, $date);
    $back = url('cash-book', ['from' => post_string('from', 10), 'to' => post_string('to', 10)]);
    if ($error !== null) {
        flash('error', $error);
        redirect($back);
    }
    $contraId = db_exec(
        'INSERT INTO contra_entries (entry_date, direction, amount, note, entered_by) VALUES (?,?,?,?,?)',
        [$date, $direction, $amount, post_string('note', 255) ?: null, (int) $_SESSION['user_id']]
    );
    record_approval('contra', $contraId, 'Waiting', $amount, (int) $_SESSION['user_id']);
    flash('success', $direction === 'Deposit' ? 'Cash deposit submitted for approval.' : 'Cash withdrawal submitted for approval.');
    redirect($back);
}

/** @return array{from: string, to: string, error: ?string} */
function book_range_from_request(): array
{
    $fallback = default_book_range();
    $from = valid_book_date((string) ($_GET['from'] ?? '')) ?? $fallback['from'];
    $to = valid_book_date((string) ($_GET['to'] ?? '')) ?? $fallback['to'];
    return ['from' => $from, 'to' => $to, 'error' => cash_book_range_error($from, $to)];
}

function action_clear_cheque(string $table, int $id): void
{
    login_required();
    if ($table !== 'expenses' && $table !== 'donations') {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $dateColumn = $table === 'expenses' ? 'expense_date' : 'donation_date';
    $row = db_one("SELECT id, payment_mode, cheque_cleared FROM {$table} WHERE id = ?", [$id]);
    $back = url($table === 'expenses' ? 'expenses' : 'donations');
    if ($row === null || $row['payment_mode'] !== 'Cheque' || (int) $row['cheque_cleared'] === 1) {
        flash('error', 'That cheque cannot be marked cleared.');
        redirect($back);
    }
    db_exec("UPDATE {$table} SET cheque_cleared = 1 WHERE id = ? AND payment_mode = 'Cheque'", [$id]);
    flash('success', 'Cheque marked cleared.');
    redirect($back);
}

function action_expense_bill(int $id): void
{
    login_required();
    $row = db_one('SELECT bill_filename FROM expenses WHERE id = ?', [$id]);
    $name = basename((string) ($row['bill_filename'] ?? ''));
    if ($row === null || $name === '' || preg_match('/^VCH-\d{4}-\d{4}\.(pdf|jpg|jpeg|png)$/', $name) !== 1) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $path = APP_ROOT . '/storage/vouchers/' . $name;
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $type = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        default => 'image/jpeg',
    };
    send_file($path, $name, $type, false, true);
}

function action_expenses(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $category = one_of(post_string('category', 80), selection_values('expense_categories'), 'Other');
        if ($amount <= 0) {
            flash('error', 'Enter an amount greater than zero.');
            redirect(url('expenses'));
        }
        $paymentMode = one_of(post_string('payment_mode', 30), money_payment_modes(), 'Cash');
        $instrument = normalize_payment_instrument(
            $paymentMode,
            post_string('upi_reference', 64),
            post_string('cheque_number', 30),
            post_string('cheque_date', 10),
            isset($_POST['cheque_cleared'])
        );
        if ($instrument['error'] !== null) {
            flash('error', $instrument['error']);
            redirect(url('expenses'));
        }
        $expenseDate = post_date('expense_date');
        $billError = posted_bill_error($_FILES['bill'] ?? null);
        if ($billError !== null) {
            flash('error', $billError);
            redirect(url('expenses'));
        }
        $pdo = db();
        $pdo->beginTransaction();
        $storedBill = null;
        try {
            $voucher = next_voucher_number($pdo, $expenseDate);
            $expenseId = db_exec(
                'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, voucher_number, cheque_number, cheque_date, cheque_cleared, upi_reference, receipt_ref, added_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $category,
                    post_string('description', 255) ?: null,
                    $amount,
                    post_string('paid_to', 150) ?: null,
                    $expenseDate,
                    $paymentMode,
                    $voucher,
                    $instrument['cheque_number'],
                    $instrument['cheque_date'],
                    $instrument['cheque_cleared'],
                    $instrument['upi_reference'],
                    post_string('receipt_ref', 100) ?: null,
                    (int) $_SESSION['user_id'],
                ]
            );
            $storedBill = store_bill_upload($_FILES['bill'] ?? null, $voucher);
            if ($storedBill !== null) {
                db_exec('UPDATE expenses SET bill_filename = ? WHERE id = ?', [$storedBill, $expenseId]);
            }
            $status = post_string('intent', 20) === 'draft' ? 'Draft' : 'Waiting';
            record_approval('expense', $expenseId, $status, $amount, (int) $_SESSION['user_id']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($storedBill !== null) {
                $path = APP_ROOT . '/storage/vouchers/' . $storedBill;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            error_log('[jt_blr] expense: ' . $e->getMessage());
            flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The expense could not be saved.');
            redirect(url('expenses'));
        }
        $savedAs = post_string('intent', 20) === 'draft' ? 'saved as a draft' : 'submitted for approval';
        flash('success', 'Expense ' . $voucher . ' ' . $savedAs . '. It enters the cash book only after approval.');
        redirect(url('expenses'));
    }
    render('expenses', [
        'title' => t('nav.expenses'),
        'pageTitle' => t('page.expenses'),
        'active' => 'expenses',
        'expenses' => db_all(
            'SELECT e.*, a.id AS approval_id, a.status AS approval_status, a.prepared_by, a.decision_note
             FROM expenses e
             LEFT JOIN approvals a ON a.subject_type = \'expense\' AND a.subject_id = e.id
             ORDER BY e.expense_date DESC, e.id DESC'
        ),
        'categories' => selection_values('expense_categories'),
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
        'title' => t('nav.bank'),
        'pageTitle' => t('page.bank'),
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
            "SELECT e.id, e.description, e.amount, e.expense_date FROM expenses e
             JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'
             WHERE e.reconciled_bank_txn_id IS NULL"
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
            $expense = db_one(
                "SELECT e.id FROM expenses e
                 JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'
                 WHERE e.id = ? AND e.reconciled_bank_txn_id IS NULL",
                [$matchId]
            );
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
        'title' => t('page.reports'),
        'pageTitle' => t('page.reports'),
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
                    one_of(post_string('plan_name', 80), selection_values('plans'), selection_values('plans')[0] ?? 'Monthly Annadaan Seva'),
                    $amount,
                    one_of(post_string('frequency', 20), selection_values('billing_cycles'), 'Monthly'),
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
        'title' => t('nav.subscriptions'),
        'pageTitle' => t('page.subscriptions'),
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
        'planPresets' => selection_values('plans'),
        'mrr' => (float) db_value("SELECT COALESCE(SUM(plan_amount),0) FROM subscribers WHERE status = 'Active' AND frequency = 'Monthly'"),
        'pendingAmount' => (float) db_value("SELECT COALESCE(SUM(amount),0) FROM subscription_invoices WHERE status IN ('Sent','Pending','Overdue')"),
        'messaging' => messaging_for_page(),
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
    if (!empty($inv['email_error'])) {
        flash('error', 'The message was logged, and the email was not sent: ' . $inv['email_error']);
        redirect(url('subscriptions'));
    }
    if (!empty($inv['email']) && smtp_is_ready(load_messaging_settings())) {
        flash('success', 'Invoice ' . $inv['invoice_number'] . ' emailed to ' . $inv['email'] . '.');
        redirect(url('subscriptions'));
    }
    flash('success', 'Invoice ' . $inv['invoice_number'] . ' logged for ' . $inv['mobile'] . $extra . '. Outgoing mail is not configured, so nothing was sent to an inbox. Use WhatsApp Web on the row to send it yourself.');
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
    $mailFailed = 0;
    foreach ($ids as $id) {
        $notice = notify_invoice((int) $id);
        if ($notice === null) {
            continue;
        }
        if (!empty($notice['email_error'])) {
            $mailFailed++;
            continue;
        }
        $sent++;
    }
    if ($mailFailed > 0) {
        flash('error', $mailFailed . ' email(s) could not be sent. Those invoices were left unchanged.');
    }
    flash('success', 'Logged ' . $sent . ' of ' . count($ids) . ' selected invoice(s).');
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
    $settings = load_messaging_settings();
    $message = invoice_notice_text($inv, $payUrl, $settings);
    notify_log('SMS', (string) $inv['mobile'], $message);
    $inv['email_error'] = null;
    if (!empty($inv['email'])) {
        $subject = 'Seva Contribution Due — ' . $inv['invoice_number'];
        if (smtp_is_ready($settings)) {
            $emailError = send_smtp_message($settings, (string) $inv['email'], $subject, $message);
            $inv['email_error'] = $emailError;
            notify_log('EMAIL', (string) $inv['email'], $emailError === null ? 'Sent. ' . $subject : 'Not sent. ' . $emailError);
            if ($emailError !== null) {
                return $inv;
            }
        } else {
            notify_log('EMAIL', (string) $inv['email'], $subject . ' | ' . $message);
        }
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
    if ($inv === null) {
        http_response_code(404);
        render('pay_invalid', [], false);
        return;
    }
    if ($inv['status'] === 'Paid') {
        render('pay_success', ['inv' => $inv, 'ref' => (string) ($inv['payment_reference'] ?? '')], false);
        return;
    }
    $paidVia = one_of(post_string('payment_method', 30), ['UPI', 'Card', 'Netbanking'], 'UPI');
    $ref = 'PAY-REF-' . strtoupper(bin2hex(random_bytes(4)));
    $saved = record_paid_invoice((int) $inv['id'], $paidVia, $ref);
    if ($saved === 'paid') {
        $again = find_invoice_by_token($token);
        render('pay_success', [
            'inv' => $again ?? $inv,
            'ref' => (string) (($again['payment_reference'] ?? '') !== '' ? $again['payment_reference'] : $ref),
        ], false);
        return;
    }
    if ($saved !== 'saved') {
        http_response_code(500);
        echo 'Payment could not be recorded.';
        return;
    }
    render('pay_success', ['inv' => $inv, 'ref' => $ref], false);
}

function record_paid_invoice(int $invoiceId, string $paidVia, string $ref): string
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $inv = db_one(
            'SELECT i.*, s.name, s.mobile, s.plan_name
             FROM subscription_invoices i
             JOIN subscribers s ON i.subscriber_id = s.id
             WHERE i.id = ? FOR UPDATE',
            [$invoiceId]
        );
        if ($inv === null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 'error';
        }
        if ($inv['status'] === 'Paid') {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 'paid';
        }
        $donor = db_one('SELECT id FROM donors WHERE phone = ? FOR UPDATE', [$inv['mobile']]);
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
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, upi_reference, receipt_generated, created_by)
             VALUES (?,?,?,?,?,?,?,0,1)',
            [
                $donorId,
                'Cash',
                $inv['amount'],
                'Subscription — ' . $inv['plan_name'] . ' (' . $inv['period_label'] . ')',
                date('Y-m-d'),
                $donationMode,
                $donationMode === 'UPI' ? $ref : null,
            ]
        );
        $marked = db()->prepare(
            "UPDATE subscription_invoices
             SET status = 'Paid', paid_date = ?, payment_reference = ?, paid_via = ?, linked_donation_id = ?
             WHERE id = ? AND status <> 'Paid'"
        );
        $marked->execute([date('Y-m-d H:i:s'), $ref, $paidVia, $donationId, $invoiceId]);
        if ($marked->rowCount() !== 1) {
            throw new RuntimeException('Invoice was already paid.');
        }
        if ($own) {
            $pdo->commit();
        }
        return 'saved';
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($own) {
            error_log('[jt_blr] pay: ' . $e->getMessage());
            return 'error';
        }
        throw $e;
    }
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
        'title' => t('page.overview'),
        'pageTitle' => t('page.overview'),
        'active' => 'demo',
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
    ]);
}

function action_language(): void
{
    $code = post_string('code', 8);
    if (!set_current_locale($code)) {
        flash('error', t('locale.keep_english'));
    }
    $next = post_string('next', 800);
    redirect(safe_next($next !== '' ? $next : null));
}

function action_localization(string $method): void
{
    admin_required();
    if ($method === 'POST') {
        $op = post_string('op', 10);
        if ($op === 'add') {
            $error = add_language(
                post_string('code', 8),
                post_string('name', 40),
                post_string('native', 40)
            );
            flash($error !== null ? 'error' : 'success', $error ?? t('locale.added'));
            $code = strtolower(post_string('code', 8));
            redirect(url('localization', $error === null ? ['code' => $code] : []));
        }
        if ($op === 'delete') {
            $error = delete_language(post_string('code', 8));
            flash($error !== null ? 'error' : 'success', $error ?? t('locale.removed'));
            redirect(url('localization'));
        }
        $code = strtolower(post_string('code', 8));
        $posted = [];
        $keys = $_POST['phrase_key'] ?? [];
        $values = $_POST['phrase_value'] ?? [];
        if (is_array($keys) && is_array($values)) {
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? null;
                if (is_string($key) && is_string($value)) {
                    $posted[$key] = mb_substr(trim($value), 0, 500);
                }
            }
        }
        $error = save_locale_phrases($code, $posted);
        flash($error !== null ? 'error' : 'success', $error ?? t('locale.saved'));
        redirect(url('localization', ['code' => $code]));
    }
    $selected = strtolower(post_string('code', 8));
    if ($selected === '' && isset($_GET['code']) && is_string($_GET['code'])) {
        $selected = strtolower(trim($_GET['code']));
    }
    if (!language_exists($selected)) {
        $selected = 'en';
    }
    render('localization', [
        'title' => t('page.localization'),
        'pageTitle' => t('page.localization'),
        'active' => 'localization',
        'languages' => language_catalog(),
        'selected' => $selected,
        'english' => english_phrases(),
        'phrases' => locale_phrases($selected),
    ]);
}

function action_settings(string $method): void
{
    admin_required();
    if ($method === 'POST' && post_string('form', 20) === 'selections') {
        $key = post_string('choice_key', 40);
        $op = post_string('op', 10);
        $index = (int) post_string('choice_index', 6);
        $label = selection_catalog()[$key]['label'] ?? 'Form choice';
        $error = apply_selection_change($key, $op, $index, [
            'name' => post_string('choice_name', 80),
            'book' => post_string('choice_book', 10),
            'direction' => post_string('choice_direction', 10),
            'store' => post_string('choice_store', 20),
            'approval' => isset($_POST['choice_approval']) ? '1' : '',
            'value' => post_string('choice_value', 30),
            'label' => post_string('choice_label', 80),
        ]);
        $done = match ($op) {
            'add' => 'Added to ' . $label . '.',
            'delete' => 'Removed from ' . $label . '.',
            default => $label . ' updated.',
        };
        flash($error !== null ? 'error' : 'success', $error ?? $done);
        redirect(url('settings') . '#choice-' . rawurlencode($key));
    }
    if ($method === 'POST' && post_string('form', 20) === 'approval') {
        $error = save_approval_limits(post_string('treasurer_limit', 20), post_string('staff_limit', 20));
        flash($error !== null ? 'error' : 'success', $error ?? 'Approval limits saved.');
        redirect(url('settings') . '#approval');
    }
    if ($method === 'POST' && post_string('form', 20) === 'brand') {
        try {
            $receiptWatermark = $_POST['watermark_receipt'] ?? '';
            $couponWatermark = $_POST['watermark_coupon'] ?? '';
            $error = save_brand_identity(
                post_string('app_name', 80),
                $_FILES['logo'] ?? null,
                isset($_POST['use_default_logo']),
                is_string($receiptWatermark) ? trim($receiptWatermark) : '',
                is_string($couponWatermark) ? trim($couponWatermark) : '',
                post_string('app_place', 80)
            );
        } catch (Throwable $e) {
            error_log('[jt_blr] brand: ' . $e->getMessage());
            flash('error', 'The name and logo could not be saved.');
            redirect(url('settings') . '#identity');
        }
        if ($error === null) {
            try {
                refresh_branded_pdfs();
            } catch (Throwable $e) {
                error_log('[jt_blr] brand documents: ' . $e->getMessage());
            }
        }
        flash($error !== null ? 'error' : 'success', $error ?? 'Temple name, place, logo, and watermark saved.');
        redirect(url('settings') . '#identity');
    }
    if ($method === 'POST') {
        $current = load_messaging_settings();
        $input = [
            'smtp_host' => post_string('smtp_host', 200),
            'smtp_port' => post_string('smtp_port', 6),
            'smtp_encryption' => post_string('smtp_encryption', 10),
            'smtp_username' => post_string('smtp_username', 200),
            'smtp_password' => post_string('smtp_password', 200),
            'smtp_from_email' => post_string('smtp_from_email', 200),
            'smtp_from_name' => post_string('smtp_from_name', 120),
            'whatsapp_country_code' => post_string('whatsapp_country_code', 8),
            'whatsapp_template' => post_string('whatsapp_template', 1000),
            'clear_smtp_password' => isset($_POST['clear_smtp_password']) ? '1' : '',
        ];
        try {
            $error = save_messaging_settings($input, $current);
        } catch (Throwable $e) {
            error_log('[jt_blr] settings: ' . $e->getMessage());
            flash('error', 'The settings could not be saved.');
            redirect(url('settings'));
        }
        flash($error !== null ? 'error' : 'success', $error ?? 'Message settings saved.');
        redirect(url('settings'));
    }
    $settings = load_messaging_settings();
    $settings['password_saved'] = $settings['smtp_password'] !== '' ? '1' : '';
    unset($settings['smtp_password']);
    render('settings', [
        'title' => t('page.settings'),
        'pageTitle' => t('page.settings'),
        'active' => 'settings',
        'settings' => $settings,
        'selectionCatalog' => selection_catalog(),
    ]);
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
                    one_of(post_string('role', 20), ['Admin', 'Treasurer', 'Staff'], 'Staff'),
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
        'title' => t('nav.users'),
        'pageTitle' => t('page.users'),
        'active' => 'users',
        'users' => $users,
        'activeCount' => $activeCount,
        'maxUsers' => MAX_ADMIN_USERS,
    ]);
}

function action_account_password(string $method): void
{
    login_required();
    if ($method === 'POST') {
        $error = change_own_password(
            (int) ($_SESSION['user_id'] ?? 0),
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        flash($error !== null ? 'error' : 'success', $error ?? 'Your password was updated.');
        redirect(url('account/password'));
    }
    render('password', [
        'title' => t('page.password'),
        'pageTitle' => t('page.password'),
        'active' => 'password',
    ]);
}

function action_set_user_password(int $userId): void
{
    admin_required();
    $error = admin_set_user_password(
        (string) ($_SESSION['role'] ?? ''),
        (int) ($_SESSION['user_id'] ?? 0),
        $userId,
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    if ($error === null) {
        $name = db_value('SELECT username FROM users WHERE id = ?', [$userId]);
        flash('success', 'Password updated for ' . (is_string($name) ? $name : 'that account') . '.');
    } else {
        flash('error', $error);
    }
    redirect(url('users'));
}

function action_toggle_user(int $userId): void
{
    admin_required();
    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        redirect(url('users'));
    }
    $currentlyActive = (int) $user['is_active'] === 1;
    $activeAdmins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND is_active = 1");
    $error = account_access_error(
        (int) ($_SESSION['user_id'] ?? 0),
        $userId,
        (string) $user['role'],
        $currentlyActive,
        $activeAdmins
    );
    if ($error !== null) {
        flash('error', $error);
        redirect(url('users'));
    }
    db_exec('UPDATE users SET is_active = ? WHERE id = ?', [$currentlyActive ? 0 : 1, $userId]);
    flash('success', $currentlyActive ? 'Account access turned off.' : 'Account access turned on.');
    redirect(url('users'));
}

function action_donor_search(): void
{
    login_required();
    $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(suggest_donors($q), JSON_UNESCAPED_UNICODE);
}

function send_pdf(string $path, string $filename, bool $download = false): never
{
    send_file($path, $filename, 'application/pdf', false, $download);
}

function send_file(string $path, string $filename, string $type, bool $deleteAfter = false, bool $download = true): never
{
    header('Content-Type: ' . $type);
    header('Cache-Control: private, no-store');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    if ($deleteAfter && is_file($path)) {
        unlink($path);
    }
    exit;
}
