<?php
declare(strict_types=1);

/**
 * Remove transactional data by scope and optional date range. Does not touch users, settings, or catalog.
 *
 * @param list<string> $scopes
 * @return array{ok:bool,message:string,counts:array<string,int>}
 */
function manage_data_purge(
    array $scopes,
    bool $allDates,
    ?string $from,
    ?string $to,
    int $staffUserId = 0,
    string $devoteeMobile = ''
): array {
    $scopes = array_values(array_unique(array_filter($scopes, static fn (string $s): bool => $s !== '')));
    $allowed = ['donations', 'bookings', 'donation_intents', 'subscription_invoices'];
    $scopes = array_values(array_intersect($scopes, $allowed));
    if ($scopes === []) {
        return ['ok' => false, 'message' => 'Choose at least one data type to remove.', 'counts' => []];
    }
    if (!$allDates) {
        $from = manage_data_valid_date($from);
        $to = manage_data_valid_date($to);
        if ($from === null || $to === null) {
            return ['ok' => false, 'message' => 'Enter valid From and To dates, or choose Remove all dates in range.', 'counts' => []];
        }
        if ($from > $to) {
            return ['ok' => false, 'message' => 'From date must be on or before To date.', 'counts' => []];
        }
    }

    $devoteeMobile = manage_data_normalize_mobile($devoteeMobile);
    if ($staffUserId < 0) {
        $staffUserId = 0;
    }

    $pdo = manage_data_pdo();
    $counts = [];
    try {
        $pdo->beginTransaction();

        if (in_array('bookings', $scopes, true)) {
            $counts += manage_data_purge_bookings($pdo, $allDates, $from, $to, $devoteeMobile);
        }
        if (in_array('donation_intents', $scopes, true)) {
            $counts += manage_data_purge_donation_intents($pdo, $allDates, $from, $to, $devoteeMobile);
        }
        if (in_array('subscription_invoices', $scopes, true)) {
            $counts += manage_data_purge_subscription_invoices($pdo, $allDates, $from, $to, $staffUserId);
        }
        if (in_array('donations', $scopes, true)) {
            $counts += manage_data_purge_donations($pdo, $allDates, $from, $to, $staffUserId, $devoteeMobile);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[manage_data] purge failed: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Purge failed: ' . $e->getMessage(), 'counts' => $counts];
    }

    manage_data_refresh_books_snapshot($pdo);

    $parts = [];
    foreach ($counts as $label => $n) {
        if ($n > 0) {
            $parts[] = $label . ': ' . $n;
        }
    }
    $msg = $parts === [] ? 'Nothing matched the selection.' : 'Removed — ' . implode('; ', $parts);

    return ['ok' => true, 'message' => $msg, 'counts' => $counts];
}

function manage_data_refresh_books_snapshot(PDO $pdo): void
{
    if (!function_exists('write_books_snapshot')) {
        return;
    }
    try {
        write_books_snapshot($pdo);
    } catch (Throwable $e) {
        error_log('[manage_data] books snapshot refresh: ' . $e->getMessage());
    }
}

/** @return array<string,int> */
function manage_data_purge_donations(
    PDO $pdo,
    bool $allDates,
    ?string $from,
    ?string $to,
    int $staffUserId = 0,
    string $devoteeMobile = ''
): array {
    $counts = ['donations' => 0, 'receipt_pdfs' => 0, 'receipts_rows' => 0];
    if (!manage_data_table_exists($pdo, 'donations')) {
        return $counts;
    }

    $joinDonor = $devoteeMobile !== '' && manage_data_table_exists($pdo, 'donors');
    $sql = 'SELECT d.id, d.receipt_number FROM donations d';
    if ($joinDonor) {
        $sql .= ' INNER JOIN donors dr ON dr.id = d.donor_id';
    }
    $sql .= ' WHERE 1=1';
    $params = [];
    if (!$allDates) {
        $sql .= ' AND d.donation_date BETWEEN ? AND ?';
        $params = [$from, $to];
    }
    if ($staffUserId > 0) {
        $sql .= ' AND d.created_by = ?';
        $params[] = $staffUserId;
    }
    if ($devoteeMobile !== '' && $joinDonor) {
        $sql .= ' AND (REPLACE(REPLACE(REPLACE(dr.phone, " ", ""), "-", ""), "+", "") LIKE ? OR dr.phone LIKE ?)';
        $params[] = '%' . $devoteeMobile . '%';
        $params[] = '%' . $devoteeMobile . '%';
    } elseif ($devoteeMobile !== '') {
        $sql .= ' AND 1=0';
    }
    $rows = $pdo->prepare($sql);
    $rows->execute($params);
    $donations = $rows->fetchAll(PDO::FETCH_ASSOC);
    if ($donations === []) {
        return $counts;
    }

    $ids = array_map(static fn (array $r): int => (int) $r['id'], $donations);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if (manage_data_table_exists($pdo, 'bank_transactions')) {
        $pdo->prepare("UPDATE bank_transactions SET matched_donation_id = NULL WHERE matched_donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'subscription_invoices')) {
        $pdo->prepare("UPDATE subscription_invoices SET linked_donation_id = NULL WHERE linked_donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'food_coupons')) {
        $pdo->prepare("UPDATE food_coupons SET donation_id = NULL WHERE donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'inventory_items')) {
        $pdo->prepare("UPDATE inventory_items SET donation_id = NULL WHERE donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'vastra_items')) {
        $pdo->prepare("UPDATE vastra_items SET donation_id = NULL WHERE donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'corrections')) {
        $pdo->prepare("DELETE FROM corrections WHERE subject_type = 'donation' AND subject_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'receipt_cancellations')) {
        $pdo->prepare("DELETE FROM receipt_cancellations WHERE donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'donation_edits')) {
        $pdo->prepare("DELETE FROM donation_edits WHERE donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'receipts')) {
        $stmt = $pdo->prepare("DELETE FROM receipts WHERE donation_id IN ($placeholders)");
        $stmt->execute($ids);
        $counts['receipts_rows'] = $stmt->rowCount();
    }
    if (manage_data_table_exists($pdo, 'bookings')) {
        $pdo->prepare("UPDATE bookings SET erp_donation_id = NULL, erp_receipt_cancel_id = NULL WHERE erp_donation_id IN ($placeholders)")
            ->execute($ids);
    }

    foreach ($donations as $row) {
        $num = trim((string) ($row['receipt_number'] ?? ''));
        if ($num !== '' && manage_data_delete_receipt_pdf($num)) {
            ++$counts['receipt_pdfs'];
        }
    }

    $stmt = $pdo->prepare("DELETE FROM donations WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $counts['donations'] = $stmt->rowCount();

    return $counts;
}

/** @return array<string,int> */
function manage_data_purge_bookings(
    PDO $pdo,
    bool $allDates,
    ?string $from,
    ?string $to,
    string $devoteeMobile = ''
): array {
    $counts = ['bookings' => 0, 'notifications' => 0, 'payments' => 0, 'booking_items' => 0];
    if (!manage_data_table_exists($pdo, 'bookings')) {
        return $counts;
    }

    $sql = 'SELECT id, erp_donation_id FROM bookings WHERE 1=1';
    $params = [];
    if (!$allDates) {
        $sql .= ' AND DATE(created_at) BETWEEN ? AND ?';
        $params = [$from, $to];
    }
    if ($devoteeMobile !== '') {
        $sql .= ' AND REPLACE(REPLACE(REPLACE(mobile, " ", ""), "-", ""), "+", "") LIKE ?';
        $params[] = '%' . $devoteeMobile . '%';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($bookings === []) {
        return $counts;
    }

    $bookingIds = array_map(static fn (array $r): int => (int) $r['id'], $bookings);
    $donationIds = [];
    foreach ($bookings as $b) {
        $did = (int) ($b['erp_donation_id'] ?? 0);
        if ($did > 0) {
            $donationIds[] = $did;
        }
    }
    if ($donationIds !== []) {
        $donationIds = array_values(array_unique($donationIds));
        $ph = implode(',', array_fill(0, count($donationIds), '?'));
        $dRows = $pdo->prepare("SELECT id, receipt_number FROM donations WHERE id IN ($ph)");
        $dRows->execute($donationIds);
        foreach ($dRows->fetchAll(PDO::FETCH_ASSOC) as $d) {
            manage_data_delete_receipt_pdf((string) $d['receipt_number']);
        }
        manage_data_purge_donation_ids($pdo, $donationIds);
    }

    $ph = implode(',', array_fill(0, count($bookingIds), '?'));
    if (manage_data_table_exists($pdo, 'notifications')) {
        $s = $pdo->prepare("DELETE FROM notifications WHERE booking_id IN ($ph)");
        $s->execute($bookingIds);
        $counts['notifications'] = $s->rowCount();
    }
    if (manage_data_table_exists($pdo, 'payments')) {
        $s = $pdo->prepare("DELETE FROM payments WHERE booking_id IN ($ph)");
        $s->execute($bookingIds);
        $counts['payments'] = $s->rowCount();
    }
    if (manage_data_table_exists($pdo, 'audit_log')) {
        $pdo->prepare("DELETE FROM audit_log WHERE booking_id IN ($ph)")->execute($bookingIds);
    }
    if (manage_data_table_exists($pdo, 'booking_items')) {
        $s = $pdo->prepare("DELETE FROM booking_items WHERE booking_id IN ($ph)");
        $s->execute($bookingIds);
        $counts['booking_items'] = $s->rowCount();
    }
    $s = $pdo->prepare("DELETE FROM bookings WHERE id IN ($ph)");
    $s->execute($bookingIds);
    $counts['bookings'] = $s->rowCount();

    return $counts;
}

/** @param list<int> $ids */
function manage_data_purge_donation_ids(PDO $pdo, array $ids): void
{
    if ($ids === [] || !manage_data_table_exists($pdo, 'donations')) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if (manage_data_table_exists($pdo, 'bank_transactions')) {
        $pdo->prepare("UPDATE bank_transactions SET matched_donation_id = NULL WHERE matched_donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'subscription_invoices')) {
        $pdo->prepare("UPDATE subscription_invoices SET linked_donation_id = NULL WHERE linked_donation_id IN ($placeholders)")
            ->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'receipts')) {
        $pdo->prepare("DELETE FROM receipts WHERE donation_id IN ($placeholders)")->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'receipt_cancellations')) {
        $pdo->prepare("DELETE FROM receipt_cancellations WHERE donation_id IN ($placeholders)")->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'donation_edits')) {
        $pdo->prepare("DELETE FROM donation_edits WHERE donation_id IN ($placeholders)")->execute($ids);
    }
    if (manage_data_table_exists($pdo, 'corrections')) {
        $pdo->prepare("DELETE FROM corrections WHERE subject_type = 'donation' AND subject_id IN ($placeholders)")
            ->execute($ids);
    }
    $pdo->prepare("DELETE FROM donations WHERE id IN ($placeholders)")->execute($ids);
}

/** @return array<string,int> */
function manage_data_purge_donation_intents(
    PDO $pdo,
    bool $allDates,
    ?string $from,
    ?string $to,
    string $devoteeMobile = ''
): array {
    $counts = ['donation_intents' => 0];
    if (!manage_data_table_exists($pdo, 'online_donation_intents')) {
        return $counts;
    }
    $sql = 'DELETE FROM online_donation_intents WHERE 1=1';
    $params = [];
    if (!$allDates) {
        $sql .= ' AND DATE(created_at) BETWEEN ? AND ?';
        $params = [$from, $to];
    }
    if ($devoteeMobile !== '') {
        $sql .= ' AND REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") LIKE ?';
        $params[] = '%' . $devoteeMobile . '%';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $counts['donation_intents'] = $stmt->rowCount();

    return $counts;
}

/** @return array<string,int> */
function manage_data_purge_subscription_invoices(
    PDO $pdo,
    bool $allDates,
    ?string $from,
    ?string $to,
    int $staffUserId = 0
): array {
    $counts = ['subscription_invoices' => 0];
    if (!manage_data_table_exists($pdo, 'subscription_invoices')) {
        return $counts;
    }

    $sql = 'SELECT i.id FROM subscription_invoices i';
    $params = [];
    if ($staffUserId > 0 && manage_data_table_exists($pdo, 'donations')) {
        $sql .= ' LEFT JOIN donations d ON d.id = i.linked_donation_id WHERE d.created_by = ?';
        $params[] = $staffUserId;
    } else {
        $sql .= ' WHERE 1=1';
    }
    if (!$allDates) {
        $sql .= ' AND i.due_date BETWEEN ? AND ?';
        $params[] = $from;
        $params[] = $to;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ids = array_map(static fn (array $r): int => (int) $r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($ids === []) {
        return $counts;
    }

    if (manage_data_table_exists($pdo, 'subscription_payments')) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM subscription_payments WHERE invoice_id IN ($ph)")->execute($ids);
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $del = $pdo->prepare("DELETE FROM subscription_invoices WHERE id IN ($ph)");
    $del->execute($ids);
    $counts['subscription_invoices'] = $del->rowCount();

    return $counts;
}

function manage_data_receipts_dir(): string
{
    return APP_ROOT . '/storage/receipts';
}

function manage_data_delete_receipt_pdf(string $receiptNumber): bool
{
    $receiptNumber = trim($receiptNumber);
    if ($receiptNumber === '' || preg_match('/^[A-Za-z0-9._-]+$/', $receiptNumber) !== 1) {
        return false;
    }
    $path = manage_data_receipts_dir() . '/' . $receiptNumber . '.pdf';
    if (!is_file($path)) {
        return false;
    }

    return @unlink($path);
}
