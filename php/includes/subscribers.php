<?php
declare(strict_types=1);

const SUBSCRIBER_INVOICE_STATUS = 'Active';

/**
 * @param array<string, mixed> $post
 * @return array{error: ?string, values: array{name: string, mobile: string, email: ?string, family_members: ?string, gotra: ?string, seva_date: ?string, plan_name: string, plan_amount: float, frequency: string, status: string}}
 */
function subscriber_input(array $post): array
{
    $text = static function (string $key, int $max) use ($post): string {
        $value = $post[$key] ?? '';
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    };
    $name = $text('name', 150);
    $mobile = $text('mobile', 15);
    $email = $text('email', 120);
    $amountRaw = $text('plan_amount', 20);
    $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : 0.0;
    $statusRaw = $text('status', 30);
    $sevaRaw = $text('seva_date', 10);
    $plan = selection_choice('plans', $text('plan_name', 80));
    $cycle = selection_choice('billing_cycles', $text('frequency', 20));
    $status = selection_choice('subscriber_statuses', $statusRaw === '' ? SUBSCRIBER_INVOICE_STATUS : $statusRaw);
    $family = $text('family_members', 300);
    $gotra = $text('gotra', 80);

    $values = [
        'name' => $name,
        'mobile' => $mobile,
        'email' => $email !== '' ? $email : null,
        'family_members' => $family !== '' ? $family : null,
        'gotra' => $gotra !== '' ? $gotra : null,
        'seva_date' => $sevaRaw !== '' ? $sevaRaw : null,
        'plan_name' => $plan ?? '',
        'plan_amount' => $amount,
        'frequency' => $cycle ?? '',
        'status' => $status ?? '',
    ];

    $error = null;
    if ($name === '' || $mobile === '' || $amount <= 0) {
        $error = 'Name, contact number, and amount are required.';
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid email, or leave it blank.';
    } elseif ($sevaRaw !== '' && !subscriber_date_is_valid($sevaRaw)) {
        $error = 'Enter a valid special date for seva, or leave it blank.';
    } elseif ($plan === null) {
        $error = 'Choose a plan from the suggestions, or add it under Settings.';
    } elseif ($cycle === null) {
        $error = 'Choose a billing cycle from the suggestions, or add it under Settings.';
    } elseif ($status === null) {
        $error = 'Choose a status from the suggestions, or add it under Settings.';
    }
    return ['error' => $error, 'values' => $values];
}

function subscriber_date_is_valid(string $value): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $value;
}

function subscriber_can_invoice(string $status): bool
{
    return $status === SUBSCRIBER_INVOICE_STATUS;
}

/** @param array{name: string, mobile: string, email: ?string, family_members: ?string, gotra: ?string, seva_date: ?string, plan_name: string, plan_amount: float, frequency: string, status: string} $values */
function add_subscriber(array $values): int
{
    return db_exec(
        'INSERT INTO subscribers (name, mobile, email, family_members, gotra, seva_date, plan_name, plan_amount, frequency, status, start_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [
            $values['name'],
            $values['mobile'],
            $values['email'],
            $values['family_members'],
            $values['gotra'],
            $values['seva_date'],
            $values['plan_name'],
            $values['plan_amount'],
            $values['frequency'],
            $values['status'],
            date('Y-m-d'),
        ]
    );
}

/**
 * Saves every field and, when the status changes, records the old status, the new one, and who changed it.
 *
 * @param array{name: string, mobile: string, email: ?string, family_members: ?string, gotra: ?string, seva_date: ?string, plan_name: string, plan_amount: float, frequency: string, status: string} $values
 * @return ?string An error for the person, or null when saved.
 */
function update_subscriber(int $id, array $values, int $userId): ?string
{
    $checked = subscriber_input($values);
    if ($checked['error'] !== null || $checked['values'] != $values) {
        return 'Check the subscriber details and try again.';
    }
    $current = db_one('SELECT id, status FROM subscribers WHERE id = ?', [$id]);
    if ($current === null) {
        return 'Subscriber not found.';
    }
    $clash = db_value('SELECT COUNT(*) FROM subscribers WHERE mobile = ? AND id <> ?', [$values['mobile'], $id]);
    if ((int) $clash > 0) {
        return 'That mobile number is already registered.';
    }
    db_exec(
        'UPDATE subscribers SET name = ?, mobile = ?, email = ?, family_members = ?, gotra = ?, seva_date = ?,
             plan_name = ?, plan_amount = ?, frequency = ?, status = ?
         WHERE id = ?',
        [
            $values['name'],
            $values['mobile'],
            $values['email'],
            $values['family_members'],
            $values['gotra'],
            $values['seva_date'],
            $values['plan_name'],
            $values['plan_amount'],
            $values['frequency'],
            $values['status'],
            $id,
        ]
    );
    $from = (string) $current['status'];
    if ($from !== $values['status']) {
        db_exec(
            'INSERT INTO subscriber_status_log (subscriber_id, from_status, to_status, changed_by) VALUES (?,?,?,?)',
            [$id, $from, $values['status'], $userId]
        );
        error_log(sprintf('subscriber %d status %s -> %s by user %d', $id, $from, $values['status'], $userId));
    }
    return null;
}

/**
 * @param list<int> $ids
 * @return array<int, array{from_status: string, to_status: string, changed_at: string, changed_by_name: string}>
 */
function latest_subscriber_status_changes(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT l.subscriber_id, l.from_status, l.to_status, l.changed_at,
                COALESCE(NULLIF(u.full_name, ''), u.username, '') AS changed_by_name
         FROM subscriber_status_log l
         LEFT JOIN users u ON u.id = l.changed_by
         WHERE l.id IN (SELECT MAX(id) FROM subscriber_status_log WHERE subscriber_id IN ({$marks}) GROUP BY subscriber_id)",
        $ids
    );
    $latest = [];
    foreach ($rows as $row) {
        $latest[(int) $row['subscriber_id']] = [
            'from_status' => (string) $row['from_status'],
            'to_status' => (string) $row['to_status'],
            'changed_at' => (string) $row['changed_at'],
            'changed_by_name' => (string) $row['changed_by_name'],
        ];
    }
    return $latest;
}

function ensure_subscriber_status_log(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS subscriber_status_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscriber_id INT NOT NULL,
            from_status VARCHAR(30) NOT NULL,
            to_status VARCHAR(30) NOT NULL,
            changed_by INT NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_subscriber_status_log_subscriber (subscriber_id),
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}
