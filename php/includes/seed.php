<?php
declare(strict_types=1);

final class Seed
{
    public static function run(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            self::users($pdo);
            self::donors($pdo);
            self::inventory($pdo);
            self::food($pdo);
            self::vastra($pdo);
            self::donations($pdo);
            self::expenses($pdo);
            self::subscriptions($pdo);
            self::operations($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        backfill_voucher_numbers($pdo);
        backfill_approvals($pdo);
    }

    private static function users(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)'
        );
        $existing = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $users = [
            ['admin', 'temple@123', 'Temple Administrator', 'Admin'],
            ['ramesh', 'ramesh@123', 'Ramesh Patra (Trustee)', 'Admin'],
            ['staff1', 'staff@123', 'Suresh (Store Keeper)', 'Staff'],
            ['treasurer', 'treasurer@123', 'Lakshmi (Treasurer)', 'Treasurer'],
        ];
        foreach ($users as [$username, $password, $name, $role]) {
            $existing->execute([$username]);
            if ($existing->fetch() !== false) {
                continue;
            }
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $role]);
        }
    }

    private static function donors(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)'
        );
        $rows = [
            ['Bikash Mohanty', '9861012345', 'bikash.m@example.com', 'Bhubaneswar', 'ABCDE1234F'],
            ['Sujata Nayak', '9437098765', 'sujata.n@example.com', 'Sarjapura, Bengaluru', null],
            ['Anil Kumar Sahoo', '9090911223', null, 'Cuttack', 'PQRSX5678K'],
            ['Meera Panda', '8895671234', 'meera.p@example.com', 'Bengaluru', null],
            ['Debashish Pattnaik', '9776655443', null, 'Bhubaneswar', null],
            ['Ranjit Kumar Behera', '9845123456', 'ranjit.b@example.com', 'Whitefield, Bengaluru', 'LMNOP9876Q'],
            ['Swagatika Rout', '9900112233', null, 'HSR Layout, Bengaluru', null],
            ['Prakash Chandra Dash', '8867234561', 'prakash.d@example.com', 'Bhubaneswar', 'XYZAB4321C'],
            ['Kabita Jena', '9778345612', null, 'Sarjapura, Bengaluru', null],
            ['Manoj Kumar Swain', '9556781234', 'manoj.s@example.com', 'Cuttack', null],
        ];
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private static function inventory(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO inventory_items (category, name, description, quantity, unit, item_condition, location, source, added_date, added_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $rows = [
            ['Kitchen Equipment', 'Commercial Gas Stove (4-burner)', 'For Mahaprasad kitchen', 2, 'pcs', 'Good', 'Kitchen', 'Purchased', self::ago(180)],
            ['Kitchen Equipment', 'Large Cooking Vessels (Handi)', '50L capacity', 6, 'pcs', 'Good', 'Kitchen', 'Purchased', self::ago(180)],
            ['Electronics', 'Microphone & PA System', 'For aarti and announcements', 1, 'set', 'Good', 'Sanctum', 'Donated', self::ago(90)],
            ['Electronics', 'LED Flood Lights', 'Courtyard lighting', 8, 'pcs', 'Good', 'Courtyard', 'Purchased', self::ago(60)],
            ['Furniture', 'Plastic Chairs', 'For events and gatherings', 150, 'pcs', 'Good', 'Store Room', 'Purchased', self::ago(120)],
            ['Furniture', 'Folding Tables', 'For prasad distribution', 10, 'pcs', 'Fair', 'Store Room', 'Purchased', self::ago(200)],
            ['Puja Items', 'Brass Lamps (Diya Stand)', 'Large ceremonial lamps', 4, 'pcs', 'Good', 'Sanctum', 'Donated', self::ago(45)],
            ['Puja Items', 'Bell Metal Bells', 'Temple bells', 3, 'pcs', 'New', 'Sanctum', 'Donated', self::ago(15)],
            ['Decoration', 'Marigold Garland Hooks', 'Ceiling mounting hooks', 40, 'pcs', 'Good', 'Store Room', 'Purchased', self::ago(30)],
            ['Decoration', 'LED String Lights', 'Festival decoration', 25, 'sets', 'Good', 'Store Room', 'Purchased', self::ago(25)],
            ['Hardware', 'Extension Boards', 'Power distribution', 12, 'pcs', 'Good', 'Store Room', 'Purchased', self::ago(90)],
            ['Hardware', 'Ladders', 'Maintenance work', 2, 'pcs', 'Fair', 'Store Room', 'Purchased', self::ago(300)],
            ['Kitchen Equipment', 'Steel Plates & Bowls', 'For prasad serving', 500, 'pcs', 'Good', 'Kitchen', 'Donated', self::ago(10)],
            ['Electronics', 'CCTV Camera System', '4-camera security setup', 1, 'set', 'New', 'Main Gate', 'Purchased', self::ago(7)],
            ['Furniture', 'Steel Almirah', 'Document & valuables storage', 2, 'pcs', 'Good', 'Office', 'Purchased', self::ago(150)],
        ];
        foreach ($rows as $row) {
            $row[] = 1;
            $stmt->execute($row);
        }
    }

    private static function food(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)'
        );
        $items = [
            ['Rice', 'kg', 120, 30],
            ['Moong Dal', 'kg', 45, 15],
            ['Ghee', 'litre', 18, 5],
            ['Sugar', 'kg', 30, 10],
            ['Vegetables (mixed)', 'kg', 25, 10],
            ['Wheat Flour (Atta)', 'kg', 8, 20],
            ['Milk', 'litre', 40, 15],
            ['Coconut', 'pcs', 60, 20],
            ['Jaggery', 'kg', 4, 10],
            ['Cooking Oil', 'litre', 22, 10],
        ];
        foreach ($items as $item) {
            $stmt->execute($item);
        }

        $log = $pdo->prepare(
            'INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)'
        );
        $usage = [
            [1, 'Used', 8, 'Daily Mahaprasad', self::ago(1)],
            [1, 'Used', 8, 'Daily Mahaprasad', self::ago(0)],
            [2, 'Used', 3, 'Daily Mahaprasad', self::ago(0)],
            [3, 'Used', 1.5, 'Daily Mahaprasad', self::ago(0)],
            [1, 'Added', 50, 'Purchased from local supplier', self::ago(5)],
            [4, 'Used', 2, 'Daily Mahaprasad', self::ago(1)],
            [5, 'Used', 4, 'Daily Mahaprasad', self::ago(0)],
            [5, 'Used', 3.5, 'Daily Mahaprasad', self::ago(1)],
            [6, 'Used', 6, 'Prasad preparation', self::ago(2)],
            [7, 'Used', 5, 'Daily Mahaprasad + Abhishek', self::ago(0)],
            [7, 'Added', 20, 'Donated by Ranjit Behera', self::ago(3)],
            [8, 'Used', 12, 'Ratha Yatra bhog', self::ago(6)],
            [9, 'Used', 1, 'Kheeri preparation', self::ago(2)],
            [10, 'Used', 3, 'Daily Mahaprasad', self::ago(1)],
            [2, 'Added', 25, 'Purchased from local supplier', self::ago(8)],
            [3, 'Added', 10, 'Donated by Swagatika Rout', self::ago(12)],
            [1, 'Used', 8, 'Daily Mahaprasad', self::ago(2)],
            [1, 'Used', 8, 'Daily Mahaprasad', self::ago(3)],
        ];
        foreach ($usage as $row) {
            $row[] = 3;
            $log->execute($row);
        }
    }

    private static function vastra(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status) VALUES (?,?,?,?,?,?,?)'
        );
        $rows = [
            ['Jagannath', 'Silk Pata (Yellow)', 'Yellow', 2, 'Donated', self::ago(20), 'In Store'],
            ['Balabhadra', 'Silk Pata (Green)', 'Green', 2, 'Donated', self::ago(20), 'In Store'],
            ['Subhadra', 'Silk Pata (Red)', 'Red', 2, 'Purchased', self::ago(45), 'In Use'],
            ['Jagannath', 'Cotton Pata (Daily)', 'White', 5, 'Purchased', self::ago(60), 'In Use'],
            ['Balabhadra', 'Cotton Pata (Daily)', 'White', 5, 'Purchased', self::ago(60), 'In Use'],
            ['Subhadra', 'Cotton Pata (Daily)', 'White', 5, 'Purchased', self::ago(60), 'In Use'],
            ['Jagannath', 'Festival Silk Pata (Gold Border)', 'Yellow/Gold', 1, 'Donated', self::ago(5), 'In Store'],
            ['Sudarshan', 'Small Pata', 'Red', 3, 'Purchased', self::ago(90), 'In Store'],
            ['Balabhadra', 'Old Silk Pata (Faded)', 'Green', 2, 'Purchased', self::ago(400), 'Retired'],
        ];
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private static function donations(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $year = date('Y');
        $rows = [
            [1, 'Cash', 5000, 'General', self::ago(18), 'UPI', "RCPT-{$year}-0001", 1],
            [2, 'Cash', 2100, 'Annadaan', self::ago(16), 'Cash', "RCPT-{$year}-0002", 1],
            [3, 'Cash', 11000, 'Ratha Yatra', self::ago(15), 'Bank Transfer', "RCPT-{$year}-0003", 1],
            [6, 'Cash', 7500, 'Vastra Seva', self::ago(14), 'UPI', "RCPT-{$year}-0004", 1],
            [7, 'Cash', 3000, 'General', self::ago(13), 'Cash', "RCPT-{$year}-0005", 1],
            [8, 'Cash', 15000, 'Construction', self::ago(12), 'Bank Transfer', "RCPT-{$year}-0006", 1],
            [9, 'Cash', 1200, 'General', self::ago(11), 'UPI', "RCPT-{$year}-0007", 1],
            [10, 'Cash', 9800, 'Annadaan', self::ago(9), 'Bank Transfer', "RCPT-{$year}-0008", 1],
            [4, 'Cash', 1500, 'General', self::ago(3), 'UPI', null, 0],
            [5, 'Cash', 21000, 'Annadaan', self::ago(1), 'Bank Transfer', null, 0],
            [6, 'Cash', 4500, 'General', self::ago(7), 'Cash', null, 0],
            [1, 'Cash', 3100, 'Ratha Yatra', self::ago(17), 'UPI', null, 0],
        ];
        foreach ($rows as $row) {
            $row[] = 1;
            $stmt->execute($row);
        }
    }

    private static function expenses(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, added_by) VALUES (?,?,?,?,?,?,?)'
        );
        $rows = [
            ['Food Supplies', 'Rice & Dal purchase', 8500, 'Sri Balaji Traders', self::ago(8), 'Bank Transfer'],
            ['Maintenance', 'Electrical repair - sanctum lighting', 3200, 'Local Electrician', self::ago(7), 'Cash'],
            ['Festival', 'Ratha Yatra decoration materials', 15600, 'Utkal Decorators', self::ago(15), 'Bank Transfer'],
            ['Utilities', 'Electricity bill', 4200, 'BESCOM', self::ago(5), 'Bank Transfer'],
            ['Utilities', 'Water bill', 1100, 'BWSSB', self::ago(5), 'Bank Transfer'],
            ['Salaries', 'Priest & staff monthly salary', 42000, 'Temple Staff', self::ago(4), 'Bank Transfer'],
            ['Decoration', 'Flowers for daily puja', 2800, 'Local Flower Vendor', self::ago(1), 'Cash'],
            ['Maintenance', 'Plumbing repair - kitchen', 1650, 'Local Plumber', self::ago(10), 'Cash'],
            ['Other', 'Printing - donation receipt books', 950, 'Sri Sai Printers', self::ago(20), 'Cash'],
        ];
        foreach ($rows as $row) {
            $row[] = 1;
            $stmt->execute($row);
        }
    }

    private static function subscriptions(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO subscribers (name, mobile, email, plan_name, plan_amount, frequency, status, start_date)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $people = [
            ['Bikash Mohanty', '9861012345', 'bikash.m@example.com', 'Monthly Annadaan Seva', 1100, 'Monthly', 'Active', self::ago(95)],
            ['Sujata Nayak', '9437098765', 'sujata.n@example.com', 'Monthly Mahaprasad Seva', 501, 'Monthly', 'Active', self::ago(65)],
            ['Ranjit Kumar Behera', '9845123456', 'ranjit.b@example.com', 'Quarterly Vastra Seva', 3000, 'Quarterly', 'Active', self::ago(150)],
            ['Prakash Chandra Dash', '8867234561', 'prakash.d@example.com', 'Monthly Annadaan Seva', 1100, 'Monthly', 'Paused', self::ago(200)],
            ['Kabita Jena', '9778345612', null, 'Monthly Deepa Seva', 251, 'Monthly', 'Active', self::ago(40)],
            ['Manoj Kumar Swain', '9556781234', 'manoj.s@example.com', 'Yearly Nitya Seva', 12000, 'Yearly', 'Cancelled', self::ago(370)],
        ];
        $ids = [];
        foreach ($people as $person) {
            $stmt->execute($person);
            $ids[] = (int) $pdo->lastInsertId();
        }

        $invoice = $pdo->prepare(
            'INSERT INTO subscription_invoices
             (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token,
              notification_sent, notification_sent_at, paid_date, paid_via, payment_reference)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $findDonor = $pdo->prepare('SELECT id FROM donors WHERE phone = ?');
        $insertDonation = $pdo->prepare(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_generated, created_by)
             VALUES (?,?,?,?,?,?,0,1)'
        );
        $link = $pdo->prepare('UPDATE subscription_invoices SET linked_donation_id = ? WHERE id = ?');

        $year = date('Y');
        $invoices = [
            [$ids[0], 1100, 'July 2026', self::ago(65), 'Paid', self::ago(66) . ' 10:00:00', self::ago(64) . ' 10:00:00', 'UPI', 'PAY-REF-88213', $people[0]],
            [$ids[0], 1100, 'August 2026', self::ago(35), 'Paid', self::ago(36) . ' 10:00:00', self::ago(34) . ' 10:00:00', 'UPI', 'PAY-REF-88540', $people[0]],
            [$ids[0], 1100, 'September 2026', self::ago(2), 'Sent', self::ago(2) . ' 10:00:00', null, null, null, $people[0]],
            [$ids[1], 501, 'August 2026', self::ago(33), 'Paid', self::ago(34) . ' 10:00:00', self::ago(32) . ' 10:00:00', 'UPI', 'PAY-REF-79021', $people[1]],
            [$ids[1], 501, 'September 2026', self::ago(1), 'Sent', self::ago(1) . ' 10:00:00', null, null, null, $people[1]],
            [$ids[2], 3000, 'Q2 2026', self::ago(80), 'Paid', self::ago(81) . ' 10:00:00', self::ago(78) . ' 10:00:00', 'Bank Transfer', 'PAY-REF-65310', $people[2]],
            [$ids[2], 3000, 'Q3 2026', self::ahead(10), 'Pending', null, null, null, null, $people[2]],
            [$ids[3], 1100, 'June 2026', self::ago(40), 'Overdue', self::ago(41) . ' 10:00:00', null, null, null, $people[3]],
            [$ids[4], 251, 'September 2026', self::ago(1), 'Sent', self::ago(1) . ' 10:00:00', null, null, null, $people[4]],
        ];

        $seq = 0;
        foreach ($invoices as $row) {
            $seq++;
            [$subId, $amount, $period, $due, $status, $sentAt, $paidAt, $paidVia, $ref, $person] = $row;
            $number = sprintf('INV-%s-%04d', $year, $seq);
            $invoice->execute([
                $subId,
                $number,
                $amount,
                $period,
                $due,
                $status,
                random_token(),
                $sentAt === null ? 0 : 1,
                $sentAt,
                $paidAt,
                $paidVia,
                $ref,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();
            if ($status !== 'Paid') {
                continue;
            }
            $findDonor->execute([$person[1]]);
            $donor = $findDonor->fetch();
            $donorId = $donor === false ? 0 : (int) $donor['id'];
            $mode = in_array($paidVia, ['Cash', 'Bank Transfer', 'UPI', 'Cheque', 'In-Kind', 'Card', 'Netbanking'], true)
                ? $paidVia
                : 'UPI';
            $insertDonation->execute([
                $donorId,
                'Cash',
                $amount,
                'Subscription — ' . $person[3] . ' (' . $period . ')',
                $paidAt !== null ? substr((string) $paidAt, 0, 10) : $due,
                $mode,
            ]);
            $link->execute([(int) $pdo->lastInsertId(), $invoiceId]);
        }
    }

    private static function operations(PDO $pdo): void
    {
        $userId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
        $itemId = self::idByName($pdo, 'inventory_items', 'name', 'Plastic Chairs');
        $foodId = self::idByName($pdo, 'food_items', 'name', 'Rice');
        $firstGift = $pdo->query('SELECT id, amount FROM donations ORDER BY id LIMIT 1')->fetch();
        $donationId = is_array($firstGift) ? (int) $firstGift['id'] : 0;
        $openDonationId = (int) $pdo->query(
            'SELECT id FROM donations WHERE receipt_number IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $annadaanDonor = self::idByName($pdo, 'donors', 'phone', '9861012345');
        $vastraDonor = self::idByName($pdo, 'donors', 'phone', '9845123456');

        $pledge = $pdo->prepare(
            'INSERT INTO pledges (donor_id, purpose, pledged_amount, pledge_date, note, created_by) VALUES (?,?,?,?,?,?)'
        );
        $pledge->execute([$annadaanDonor, 'Annadaan', 25000, self::ago(40), 'Pledge for the year', $userId]);
        $pledgeId = (int) $pdo->lastInsertId();
        $pledge->execute([$vastraDonor, 'Vastra Seva', 10000, self::ago(21), null, $userId]);
        if ($openDonationId > 0) {
            $pdo->prepare('UPDATE donations SET pledge_id = ? WHERE id = ?')->execute([$pledgeId, $openDonationId]);
        }

        if ($itemId > 0) {
            $move = $pdo->prepare(
                'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)'
            );
            $move->execute([$itemId, 'Added', 150, 'Opening stock of chairs', self::ago(120), $userId]);
            $move->execute([$itemId, 'Issued', 20, 'Ratha Yatra seating', self::ago(14), $userId]);

            $purchase = $pdo->prepare(
                'INSERT INTO purchases (item_id, item_name, category, unit, quantity, unit_cost, amount, location, paid_to, purchase_date, payment_mode, prepared_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $purchase->execute([
                $itemId, 'Plastic Chairs', 'Furniture', 'pcs', 50, 180, 9000,
                'Store Room', 'Local Furniture Mart', self::ago(120), 'Bank Transfer', $userId,
            ]);
            $purchaseId = (int) $pdo->lastInsertId();
            self::approval($pdo, 'purchase', $purchaseId, 'Approved', 9000, $userId);
        }

        if ($foodId > 0) {
            $stock = $pdo->prepare(
                'INSERT INTO stock_requests (store_name, item_id, item_name, movement_type, quantity, note, movement_date, prepared_by, applied)
                 VALUES (?,?,?,?,?,?,?,?,0)'
            );
            $stock->execute(['food', $foodId, 'Rice', 'Lost', 2, 'Spillage during storage', self::ago(1), $userId]);
            self::approval($pdo, 'stock', (int) $pdo->lastInsertId(), 'Waiting', 0, $userId);
        }

        $year = financial_year_label(date('Y-m-d'));
        $pdo->prepare(
            'INSERT INTO opening_balances (financial_year, cash_amount, bank_amount, note, set_by) VALUES (?,?,?,?,?)'
        )->execute([$year, 25000, 180000, 'Opening balance brought forward', $userId]);

        $contra = $pdo->prepare(
            'INSERT INTO contra_entries (entry_date, direction, amount, note, entered_by) VALUES (?,?,?,?,?)'
        );
        $contra->execute([self::ago(6), 'Deposit', 10000, 'Cash deposited to the temple bank account', $userId]);
        $contra->execute([self::ago(2), 'Withdraw', 2000, 'Cash drawn for daily puja', $userId]);

        $pdo->prepare(
            'INSERT INTO food_coupon_batches (coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, created_date, created_by, issued_unix)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute(['Mahaprasad coupon', 50, 1, 10, 10, 500, self::ago(0), $userId, time()]);

        if ($donationId > 0) {
            $correction = $pdo->prepare(
                'INSERT INTO corrections (subject_type, subject_id, original_amount, corrected_amount, reason, entry_date, prepared_by)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $original = round((float) ($firstGift['amount'] ?? 0), 2);
            $correction->execute([
                'donation',
                $donationId,
                $original,
                $original + 100,
                'Counted again and found 100 rupees more.',
                self::ago(1),
                $userId,
            ]);
            self::approval($pdo, 'correction', (int) $pdo->lastInsertId(), 'Approved', 100, $userId);

            $cancel = $pdo->prepare(
                'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)'
            );
            $cancel->execute([$openDonationId > 0 ? $openDonationId : $donationId, 'Devotee asked for the gift to be recorded again.', $userId]);
            self::approval($pdo, 'receipt', (int) $pdo->lastInsertId(), 'Waiting', 0, $userId);
        }

        $receipts = $pdo->query(
            'SELECT id, receipt_number, created_by FROM donations WHERE receipt_number IS NOT NULL'
        );
        $saveReceipt = $pdo->prepare(
            'INSERT INTO receipts (donation_id, receipt_number, generated_by) VALUES (?,?,?)'
        );
        if ($receipts !== false) {
            foreach ($receipts as $receipt) {
                $saveReceipt->execute([(int) $receipt['id'], (string) $receipt['receipt_number'], $userId]);
            }
        }

        $upload = $pdo->prepare(
            'INSERT INTO bank_statement_uploads (filename, uploaded_by, total_transactions) VALUES (?,?,?)'
        );
        $upload->execute(['temple-bank-statement.csv', $userId, 3]);
        $batchId = (int) $pdo->lastInsertId();
        $bank = $pdo->prepare(
            'INSERT INTO bank_transactions (upload_batch_id, txn_date, description, amount, txn_type, balance, reconciled_status)
             VALUES (?,?,?,?,?,?,?)'
        );
        $bank->execute([$batchId, self::ago(12), 'NEFT donation', 15000, 'Credit', 195000, 'Unmatched']);
        $bank->execute([$batchId, self::ago(8), 'Rice supplier', 8500, 'Debit', 186500, 'Unmatched']);
        $bank->execute([$batchId, self::ago(6), 'Cash deposit', 10000, 'Credit', 196500, 'Unmatched']);

        $pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_key = setting_key'
        )->execute(['brand_name', 'Shree Jagannath Temple']);
    }

    private static function idByName(PDO $pdo, string $table, string $column, string $name): int
    {
        $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE ' . $column . ' = ? LIMIT 1');
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }

    private static function approval(PDO $pdo, string $type, int $subjectId, string $status, float $amount, int $userId): void
    {
        $pdo->prepare(
            'INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by) VALUES (?,?,?,?,?)'
        )->execute([$type, $subjectId, $status, $amount, $userId]);
    }

    private static function ago(int $days): string
    {
        return (new DateTimeImmutable('today'))->modify('-' . $days . ' days')->format('Y-m-d');
    }

    private static function ahead(int $days): string
    {
        return (new DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');
    }
}
