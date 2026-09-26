<?php
declare(strict_types=1);

function pledge_request_error(float $amount, string $date, string $purpose): ?string
{
    if ($amount <= 0 || $amount > 99999999.99) {
        return 'Enter the amount promised.';
    }
    if (valid_book_date($date) === null) {
        return 'Enter a valid date.';
    }
    if (trim($purpose) === '') {
        return 'Enter what the promise is for.';
    }
    return null;
}

function pledge_receipt_error(float $amount, string $date, string $paymentMode): ?string
{
    if ($amount <= 0 || $amount > 99999999.99) {
        return 'Enter the amount received.';
    }
    if (valid_book_date($date) === null) {
        return 'Enter a valid date.';
    }
    if (book_account($paymentMode) === null) {
        return 'A pledge is received in cash or through the bank.';
    }
    return null;
}

function pledge_outstanding(float $pledged, float $received): float
{
    return round($pledged - $received, 2);
}

function pledge_is_visible(string $pledgeDate, float $outstanding, string $from, string $to): bool
{
    if ($pledgeDate >= $from && $pledgeDate <= $to) {
        return true;
    }
    return $outstanding > 0.009;
}

/**
 * @param array<string, mixed> $donor
 * @param list<array<string, mixed>> $gifts
 * @param list<array<string, mixed>> $corrections
 * @param list<array<string, mixed>> $pledges
 * @param list<array<string, mixed>> $receipts donations linked to a pledge, any date
 * @return array<string, mixed>
 */
function build_donor_statement(
    array $donor,
    string $from,
    string $to,
    array $gifts,
    array $corrections,
    array $pledges,
    array $receipts
): array {
    $error = cash_book_range_error($from, $to);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }
    $lines = [];
    $received = 0.0;
    foreach ($gifts as $gift) {
        $date = (string) ($gift['donation_date'] ?? '');
        if ($date < $from || $date > $to) {
            continue;
        }
        $amount = round((float) ($gift['amount'] ?? 0), 2);
        $inBook = book_account((string) ($gift['payment_mode'] ?? '')) !== null && $amount > 0;
        if ($inBook) {
            $received += $amount;
        }
        $lines[] = [
            'date' => $date,
            'sort' => 100000000 + (int) ($gift['id'] ?? 0),
            'kind' => 'gift',
            'purpose' => trim((string) ($gift['purpose'] ?? '')) !== '' ? (string) $gift['purpose'] : 'General',
            'amount' => $amount,
            'in_book' => $inBook,
            'payment_mode' => (string) ($gift['payment_mode'] ?? ''),
            'receipt_number' => (string) ($gift['receipt_number'] ?? ''),
            'receipt_cancelled' => (int) ($gift['receipt_cancelled'] ?? 0) === 1,
            'note' => '',
        ];
    }
    $deltaByDonation = [];
    foreach ($corrections as $row) {
        $date = (string) ($row['entry_date'] ?? '');
        $delta = round((float) ($row['corrected_amount'] ?? 0) - (float) ($row['original_amount'] ?? 0), 2);
        $donationId = (int) ($row['subject_id'] ?? 0);
        if (book_account((string) ($row['payment_mode'] ?? '')) !== null && abs($delta) >= 0.01) {
            $deltaByDonation[$donationId] = round(($deltaByDonation[$donationId] ?? 0.0) + $delta, 2);
        }
        if ($date < $from || $date > $to || abs($delta) < 0.01) {
            continue;
        }
        if (book_account((string) ($row['payment_mode'] ?? '')) === null) {
            continue;
        }
        $received += $delta;
        $lines[] = [
            'date' => $date,
            'sort' => 300000000 + (int) ($row['id'] ?? 0),
            'kind' => 'correction',
            'purpose' => 'Correction',
            'amount' => $delta,
            'in_book' => true,
            'payment_mode' => (string) ($row['payment_mode'] ?? ''),
            'receipt_number' => (string) ($row['receipt_number'] ?? ''),
            'receipt_cancelled' => false,
            'note' => trim((string) ($row['reason'] ?? '')),
        ];
    }
    usort($lines, static function (array $a, array $b): int {
        return [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']];
    });

    $receivedByPledge = [];
    foreach ($receipts as $receipt) {
        $pledgeId = (int) ($receipt['pledge_id'] ?? 0);
        if (book_account((string) ($receipt['payment_mode'] ?? '')) === null) {
            continue;
        }
        $net = round((float) ($receipt['amount'] ?? 0) + ($deltaByDonation[(int) ($receipt['id'] ?? 0)] ?? 0.0), 2);
        $receivedByPledge[$pledgeId] = round(($receivedByPledge[$pledgeId] ?? 0.0) + $net, 2);
    }
    $pledgeLines = [];
    foreach ($pledges as $pledge) {
        $pledged = round((float) ($pledge['pledged_amount'] ?? 0), 2);
        $got = $receivedByPledge[(int) ($pledge['id'] ?? 0)] ?? 0.0;
        $outstanding = pledge_outstanding($pledged, $got);
        $date = (string) ($pledge['pledge_date'] ?? '');
        if (!pledge_is_visible($date, $outstanding, $from, $to)) {
            continue;
        }
        $pledgeLines[] = [
            'id' => (int) ($pledge['id'] ?? 0),
            'date' => $date,
            'purpose' => (string) ($pledge['purpose'] ?? ''),
            'pledged' => $pledged,
            'received' => $got,
            'outstanding' => $outstanding,
            'note' => (string) ($pledge['note'] ?? ''),
        ];
    }
    usort($pledgeLines, static function (array $a, array $b): int {
        return [$a['date'], $a['id']] <=> [$b['date'], $b['id']];
    });

    return [
        'donor_id' => (int) ($donor['id'] ?? 0),
        'name' => (string) ($donor['name'] ?? ''),
        'phone' => (string) ($donor['phone'] ?? ''),
        'email' => (string) ($donor['email'] ?? ''),
        'address' => (string) ($donor['address'] ?? ''),
        'pan' => (string) ($donor['pan_number'] ?? ''),
        'from' => $from,
        'to' => $to,
        'financial_year' => financial_year_label($from),
        'lines' => $lines,
        'received' => round($received, 2),
        'pledges' => $pledgeLines,
    ];
}

function ensure_donor_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pledges (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            donor_id        INT NOT NULL,
            purpose         VARCHAR(200) NOT NULL,
            pledged_amount  DECIMAL(12,2) NOT NULL,
            pledge_date     DATE NOT NULL,
            note            VARCHAR(255) NULL,
            created_by      INT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES donors(id),
            FOREIGN KEY (created_by) REFERENCES users(id),
            KEY idx_pledge_donor (donor_id),
            KEY idx_pledge_date (pledge_date)
        ) ENGINE=InnoDB"
    );
    ensure_column($pdo, 'donations', 'pledge_id', 'INT NULL');
    $fk = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donations'
           AND COLUMN_NAME = 'pledge_id' AND REFERENCED_TABLE_NAME = 'pledges'"
    )->fetch();
    if ($fk === false) {
        $pdo->exec('ALTER TABLE donations ADD CONSTRAINT fk_donation_pledge FOREIGN KEY (pledge_id) REFERENCES pledges(id)');
    }
}

/** @return array<string, mixed> */
function load_donor_statement(int $donorId, string $from, string $to): array
{
    $donor = db_one('SELECT * FROM donors WHERE id = ?', [$donorId]);
    if ($donor === null) {
        throw new RuntimeException('That devotee was not found.');
    }
    $gifts = db_all(
        'SELECT id, donation_date, amount, purpose, payment_mode, donation_type, receipt_number, receipt_cancelled, pledge_id
         FROM donations
         WHERE donor_id = ? AND donation_date BETWEEN ? AND ?
         ORDER BY donation_date, id',
        [$donorId, $from, $to]
    );
    $corrections = db_all(
        "SELECT c.id, c.subject_id, c.entry_date, c.original_amount, c.corrected_amount, c.reason,
                d.payment_mode, d.receipt_number, d.pledge_id
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         WHERE d.donor_id = ?",
        [$donorId]
    );
    $pledges = db_all(
        'SELECT id, purpose, pledged_amount, pledge_date, note FROM pledges WHERE donor_id = ? ORDER BY pledge_date, id',
        [$donorId]
    );
    $receipts = db_all(
        'SELECT id, pledge_id, amount, payment_mode FROM donations WHERE donor_id = ? AND pledge_id IS NOT NULL',
        [$donorId]
    );
    return build_donor_statement($donor, $from, $to, $gifts, $corrections, $pledges, $receipts);
}

/** @return list<array<string, mixed>> */
/** @return list<array{id: int, label: string, donor_name: string}> */
function open_pledge_choices(): array
{
    $received = [];
    foreach (db_all(
        "SELECT pledge_id, COALESCE(SUM(amount), 0) AS total
         FROM donations
         WHERE pledge_id IS NOT NULL
           AND payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
           AND amount IS NOT NULL
         GROUP BY pledge_id"
    ) as $row) {
        $received[(int) $row['pledge_id']] = (float) $row['total'];
    }
    foreach (db_all(
        "SELECT d.pledge_id, COALESCE(SUM(c.corrected_amount - c.original_amount), 0) AS delta
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         WHERE d.pledge_id IS NOT NULL
           AND d.payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
         GROUP BY d.pledge_id"
    ) as $row) {
        $id = (int) $row['pledge_id'];
        $received[$id] = round(($received[$id] ?? 0.0) + (float) $row['delta'], 2);
    }
    $choices = [];
    foreach (db_all(
        'SELECT p.id, p.purpose, p.pledged_amount, d.name
         FROM pledges p JOIN donors d ON d.id = p.donor_id
         ORDER BY d.name, p.pledge_date, p.id'
    ) as $row) {
        $left = pledge_outstanding((float) $row['pledged_amount'], $received[(int) $row['id']] ?? 0.0);
        if ($left <= 0.009) {
            continue;
        }
        $choices[] = [
            'id' => (int) $row['id'],
            'donor_name' => (string) $row['name'],
            'label' => (string) $row['name'] . ' · ' . (string) $row['purpose'] . ' · still ' . money($left),
        ];
    }
    return $choices;
}

function load_donor_list(string $from, string $to, string $query = ''): array
{
    $donors = db_all('SELECT id, name, phone, pan_number FROM donors ORDER BY name, id');
    $received = [];
    foreach (db_all(
        "SELECT donor_id, COALESCE(SUM(amount), 0) AS total
         FROM donations
         WHERE donation_date BETWEEN ? AND ?
           AND payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
           AND amount IS NOT NULL
         GROUP BY donor_id",
        [$from, $to]
    ) as $row) {
        $received[(int) $row['donor_id']] = (float) $row['total'];
    }
    foreach (db_all(
        "SELECT d.donor_id, COALESCE(SUM(c.corrected_amount - c.original_amount), 0) AS delta
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         WHERE c.entry_date BETWEEN ? AND ?
           AND d.payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
         GROUP BY d.donor_id",
        [$from, $to]
    ) as $row) {
        $id = (int) $row['donor_id'];
        $received[$id] = round(($received[$id] ?? 0.0) + (float) $row['delta'], 2);
    }
    $promised = [];
    foreach (db_all('SELECT donor_id, COALESCE(SUM(pledged_amount), 0) AS total FROM pledges GROUP BY donor_id') as $row) {
        $promised[(int) $row['donor_id']] = (float) $row['total'];
    }
    foreach (db_all(
        "SELECT d.donor_id, COALESCE(SUM(d.amount), 0) AS total
         FROM donations d
         WHERE d.pledge_id IS NOT NULL
           AND d.payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
           AND d.amount IS NOT NULL
         GROUP BY d.donor_id"
    ) as $row) {
        $id = (int) $row['donor_id'];
        $promised[$id] = round(($promised[$id] ?? 0.0) - (float) $row['total'], 2);
    }
    foreach (db_all(
        "SELECT d.donor_id, COALESCE(SUM(c.corrected_amount - c.original_amount), 0) AS delta
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         WHERE d.pledge_id IS NOT NULL
           AND d.payment_mode IN ('Cash','Bank Transfer','UPI','Cheque','Card','Netbanking')
         GROUP BY d.donor_id"
    ) as $row) {
        $id = (int) $row['donor_id'];
        $promised[$id] = round(($promised[$id] ?? 0.0) - (float) $row['delta'], 2);
    }
    $needle = mb_strtolower(trim($query));
    $rows = [];
    foreach ($donors as $donor) {
        $name = (string) $donor['name'];
        $phone = (string) ($donor['phone'] ?? '');
        if ($needle !== '' && !str_contains(mb_strtolower($name . ' ' . $phone), $needle)) {
            continue;
        }
        $rows[] = [
            'id' => (int) $donor['id'],
            'name' => $name,
            'phone' => $phone,
            'pan' => (string) ($donor['pan_number'] ?? ''),
            'received' => round($received[(int) $donor['id']] ?? 0.0, 2),
            'outstanding' => max(0.0, round($promised[(int) $donor['id']] ?? 0.0, 2)),
        ];
    }
    return $rows;
}
