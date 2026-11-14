<?php
declare(strict_types=1);

/**
 * Admin-only food coupon removal (scanned + unscanned) with linked donation/ledger rows.
 *
 * @return array{ok:bool,message:string,counts:array<string,int|list<string>>}
 */
function manage_data_purge_coupons(string $scope, string $input, bool $purgeEntireBatch): array
{
    $scope = match ($scope) {
        'codes', 'batch', 'all' => $scope,
        default => '',
    };
    if ($scope === '') {
        return ['ok' => false, 'message' => 'Choose how to select coupons.', 'counts' => []];
    }

    if (!manage_data_table_exists(manage_data_pdo(), 'food_coupons')
        || !manage_data_table_exists(manage_data_pdo(), 'food_coupon_batches')) {
        return ['ok' => false, 'message' => 'Coupon tables are not present in this database.', 'counts' => []];
    }

    $pdo = manage_data_pdo();
    $batchIds = [];
    $couponIds = [];

    if ($scope === 'all') {
        $batchIds = manage_data_coupon_all_batch_ids($pdo);
    } elseif ($scope === 'batch') {
        $batchId = manage_data_coupon_resolve_batch_id($pdo, $input);
        if ($batchId === null) {
            return ['ok' => false, 'message' => 'Batch not found. Enter batch ID, coupon name, or any code from that batch.', 'counts' => []];
        }
        $batchIds = [$batchId];
    } else {
        $codes = manage_data_coupon_parse_codes($input);
        if ($codes === []) {
            return ['ok' => false, 'message' => 'Enter one or more coupon codes (e.g. CU-1790447400-0006), one per line.', 'counts' => []];
        }
        $found = manage_data_coupon_ids_for_codes($pdo, $codes);
        $missing = array_values(array_diff($codes, $found['codes']));
        if ($found['ids'] === [] && $missing !== []) {
            return ['ok' => false, 'message' => 'No matching coupons: ' . implode(', ', $missing), 'counts' => []];
        }
        $couponIds = $found['ids'];
        if ($purgeEntireBatch && $found['batch_ids'] !== []) {
            $batchIds = $found['batch_ids'];
            $couponIds = [];
        }
    }

    if ($batchIds !== []) {
        $couponIds = manage_data_coupon_ids_for_batches($pdo, $batchIds);
    }

    $couponIds = array_values(array_unique(array_filter($couponIds, static fn (int $i): bool => $i > 0)));
    $batchIds = array_values(array_unique(array_filter($batchIds, static fn (int $i): bool => $i > 0)));

    if ($couponIds === [] && $batchIds === []) {
        return ['ok' => false, 'message' => 'Nothing matched.', 'counts' => []];
    }

    $counts = [
        'coupons' => 0,
        'batches' => 0,
        'donations' => 0,
        'receipt_pdfs' => 0,
        'approvals' => 0,
        'batch_pdfs' => 0,
        'codes_removed' => [],
    ];

    try {
        $pdo->beginTransaction();

        $donationIds = manage_data_coupon_linked_donation_ids($pdo, $couponIds);
        if ($couponIds !== []) {
            $ph = implode(',', array_fill(0, count($couponIds), '?'));
            $pdo->prepare("UPDATE food_coupons SET donation_id = NULL WHERE id IN ($ph)")->execute($couponIds);
        }
        if ($donationIds !== []) {
            $dCounts = manage_data_delete_donations_deep($pdo, $donationIds);
            $counts['donations'] = (int) ($dCounts['donations'] ?? 0);
            $counts['receipt_pdfs'] = (int) ($dCounts['receipt_pdfs'] ?? 0);
        }

        if ($couponIds !== []) {
            $ph = implode(',', array_fill(0, count($couponIds), '?'));
            $rows = $pdo->prepare("SELECT code FROM food_coupons WHERE id IN ($ph)");
            $rows->execute($couponIds);
            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $code) {
                $counts['codes_removed'][] = (string) $code;
            }
            $del = $pdo->prepare("DELETE FROM food_coupons WHERE id IN ($ph)");
            $del->execute($couponIds);
            $counts['coupons'] = $del->rowCount();
        }

        if ($batchIds !== []) {
            $ph = implode(',', array_fill(0, count($batchIds), '?'));
            if (manage_data_table_exists($pdo, 'approvals')) {
                $stmt = $pdo->prepare("DELETE FROM approvals WHERE subject_type = 'coupon' AND subject_id IN ($ph)");
                $stmt->execute($batchIds);
                $counts['approvals'] = $stmt->rowCount();
            }
            foreach ($batchIds as $bid) {
                if (manage_data_forget_coupon_pdf($bid)) {
                    ++$counts['batch_pdfs'];
                }
            }
            $pdo->prepare("DELETE FROM food_coupon_batches WHERE id IN ($ph)")->execute($batchIds);
            $counts['batches'] = count($batchIds);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[manage_data] coupon purge: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Coupon purge failed: ' . $e->getMessage(), 'counts' => $counts];
    }

    manage_data_refresh_books_snapshot($pdo);

    $msg = sprintf(
        'Removed %d coupon(s)',
        (int) $counts['coupons']
    );
    if ($counts['batches'] > 0) {
        $msg .= ', ' . $counts['batches'] . ' batch(es)';
    }
    if ($counts['donations'] > 0) {
        $msg .= ', ' . $counts['donations'] . ' linked donation(s) from the books';
    }
    if ($counts['codes_removed'] !== []) {
        $sample = array_slice($counts['codes_removed'], 0, 8);
        $msg .= ' — ' . implode(', ', $sample);
        if (count($counts['codes_removed']) > 8) {
            $msg .= ', …';
        }
    }

    return ['ok' => true, 'message' => $msg, 'counts' => $counts];
}

/** @return list<string> */
function manage_data_coupon_parse_codes(string $raw): array
{
    $raw = str_replace(["\r\n", "\r"], "\n", trim($raw));
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $codes = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $norm = manage_data_coupon_normalize_code($part);
        if ($norm !== '' && manage_data_coupon_code_is_valid($norm)) {
            $codes[] = $norm;
        }
    }

    return array_values(array_unique($codes));
}

function manage_data_coupon_normalize_code(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/(CU-\d{9,12}-\d{4,6})/i', $raw, $match) === 1) {
        return strtoupper($match[1]);
    }

    return strtoupper($raw);
}

function manage_data_coupon_code_is_valid(string $code): bool
{
    return preg_match('/^CU-\d{9,12}-\d{4,6}$/', $code) === 1;
}

function manage_data_coupon_resolve_batch_id(PDO $pdo, string $input): ?int
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('/^\d{1,9}$/', $input) === 1) {
        $id = (int) $input;
        $ok = $pdo->prepare('SELECT id FROM food_coupon_batches WHERE id = ? LIMIT 1');
        $ok->execute([$id]);

        return $ok->fetchColumn() !== false ? $id : null;
    }
    if (manage_data_coupon_code_is_valid(manage_data_coupon_normalize_code($input))) {
        $code = manage_data_coupon_normalize_code($input);
        $stmt = $pdo->prepare(
            'SELECT c.batch_id FROM food_coupons c WHERE c.code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $bid = $stmt->fetchColumn();

        return $bid !== false ? (int) $bid : null;
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM food_coupon_batches WHERE coupon_name LIKE ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['%' . $input . '%']);
    $bid = $stmt->fetchColumn();

    return $bid !== false ? (int) $bid : null;
}

/** @return list<int> */
function manage_data_coupon_all_batch_ids(PDO $pdo): array
{
    $ids = $pdo->query('SELECT id FROM food_coupon_batches ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

    return array_map('intval', $ids ?: []);
}

/**
 * @param list<string> $codes
 * @return array{ids:list<int>,codes:list<string>,batch_ids:list<int>}
 */
function manage_data_coupon_ids_for_codes(PDO $pdo, array $codes): array
{
    $ids = [];
    $foundCodes = [];
    $batchIds = [];
    $stmt = $pdo->prepare('SELECT id, code, batch_id FROM food_coupons WHERE code = ? LIMIT 1');
    foreach ($codes as $code) {
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }
        $ids[] = (int) $row['id'];
        $foundCodes[] = (string) $row['code'];
        $batchIds[] = (int) $row['batch_id'];
    }

    return [
        'ids' => $ids,
        'codes' => $foundCodes,
        'batch_ids' => array_values(array_unique($batchIds)),
    ];
}

/** @param list<int> $batchIds @return list<int> */
function manage_data_coupon_ids_for_batches(PDO $pdo, array $batchIds): array
{
    if ($batchIds === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($batchIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM food_coupons WHERE batch_id IN ($ph)");
    $stmt->execute($batchIds);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/** @param list<int> $couponIds @return list<int> */
function manage_data_coupon_linked_donation_ids(PDO $pdo, array $couponIds): array
{
    if ($couponIds === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($couponIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT donation_id FROM food_coupons WHERE id IN ($ph) AND donation_id IS NOT NULL"
    );
    $stmt->execute($couponIds);

    return array_values(array_filter(
        array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []),
        static fn (int $i): bool => $i > 0
    ));
}

/** @return list<array<string,mixed>> */
function manage_data_coupon_batch_preview(PDO $pdo, int $limit = 40): array
{
    if (!manage_data_table_exists($pdo, 'food_coupon_batches')) {
        return [];
    }
    $sql = "SELECT b.id, b.coupon_name, b.created_date, b.quantity,
                   a.status AS approval_status,
                   SUM(CASE WHEN c.status = 'Redeemed' THEN 1 ELSE 0 END) AS redeemed
            FROM food_coupon_batches b
            LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
            LEFT JOIN food_coupons c ON c.batch_id = b.id
            GROUP BY b.id, b.coupon_name, b.created_date, b.quantity, a.status
            ORDER BY b.id DESC
            LIMIT " . max(1, min(200, $limit));

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function manage_data_forget_coupon_pdf(int $batchId): bool
{
    if ($batchId < 1) {
        return false;
    }
    $path = APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
    if (!is_file($path)) {
        return false;
    }

    return @unlink($path);
}