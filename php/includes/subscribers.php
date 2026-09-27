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

const SUBSCRIPTION_INVOICE_STATUSES = ['Pending', 'Sent', 'Overdue', 'Paid', 'Failed'];
const INVOICE_RECEIPT_FILTERS = ['with', 'without'];

/**
 * Reads the invoice filters from the query string. Unknown values are dropped.
 *
 * @param array<string, mixed> $get
 * @return array{q: string, status: string, period: string, from: string, to: string, receipt: string}
 */
function invoice_filters(array $get): array
{
    $text = static function (string $key, int $max) use ($get): string {
        $value = $get[$key] ?? '';
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    };
    $status = '';
    foreach (SUBSCRIPTION_INVOICE_STATUSES as $known) {
        if (strcasecmp($known, $text('status', 20)) === 0) {
            $status = $known;
        }
    }
    $date = static fn (string $value): string => subscriber_date_is_valid($value) ? $value : '';
    $from = $date($text('from', 10));
    $to = $date($text('to', 10));
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    $receipt = $text('receipt', 10);
    return [
        'q' => $text('q', 60),
        'status' => $status,
        'period' => $text('period', 30),
        'from' => $from,
        'to' => $to,
        'receipt' => in_array($receipt, INVOICE_RECEIPT_FILTERS, true) ? $receipt : '',
    ];
}

/** @param array{q: string, status: string, period: string, from: string, to: string, receipt: string} $filters */
function invoice_filters_active(array $filters): bool
{
    return implode('', $filters) !== '';
}

/**
 * @param array{q: string, status: string, period: string, from: string, to: string, receipt: string} $filters
 * @return list<array<string, mixed>>
 */
function invoice_list(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['q'] !== '') {
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        $where[] = '(s.name LIKE ? OR s.mobile LIKE ? OR s.email LIKE ? OR i.invoice_number LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['status'] !== '') {
        $where[] = 'i.status = ?';
        $params[] = $filters['status'];
    }
    if ($filters['period'] !== '') {
        $where[] = 'i.period_label = ?';
        $params[] = $filters['period'];
    }
    if ($filters['from'] !== '') {
        $where[] = 'i.due_date >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[] = 'i.due_date <= ?';
        $params[] = $filters['to'];
    }
    if ($filters['receipt'] === 'with') {
        $where[] = 'd.receipt_generated = 1';
    } elseif ($filters['receipt'] === 'without') {
        $where[] = "(i.status = 'Paid' AND (d.id IS NULL OR d.receipt_generated = 0))";
    }
    $sql = "SELECT i.*, s.name AS subscriber_name, s.mobile, s.email,
                   d.receipt_number, d.receipt_generated, d.receipt_cancelled
            FROM subscription_invoices i
            JOIN subscribers s ON i.subscriber_id = s.id
            LEFT JOIN donations d ON d.id = i.linked_donation_id"
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . " ORDER BY (i.status = 'Overdue') DESC, (i.status = 'Sent') DESC, (i.status = 'Pending') DESC, i.due_date DESC, i.id DESC";
    return db_all($sql, $params);
}

/** @return list<string> Stored periods, newest first. */
function invoice_periods(): array
{
    $rows = db_all(
        "SELECT period_label FROM subscription_invoices
         WHERE period_label IS NOT NULL AND period_label <> ''
         GROUP BY period_label ORDER BY MAX(due_date) DESC"
    );
    return array_map(static fn (array $r): string => (string) $r['period_label'], $rows);
}

/**
 * Creates this period's invoice for an Active subscriber, or returns the unpaid one already made for it.
 *
 * @return array{error: ?string, id: int, number: string, reused: bool}
 */
function create_subscription_invoice(int $subscriberId): array
{
    $none = ['id' => 0, 'number' => '', 'reused' => false];
    $sub = db_one('SELECT id, status, plan_amount FROM subscribers WHERE id = ?', [$subscriberId]);
    if ($sub === null) {
        return ['error' => 'Subscriber not found.'] + $none;
    }
    if (!subscriber_can_invoice((string) $sub['status'])) {
        return ['error' => 'Only an Active subscriber can get a new invoice. Update the status first.'] + $none;
    }
    $period = date('F Y');
    $open = db_one(
        "SELECT id, invoice_number FROM subscription_invoices
         WHERE subscriber_id = ? AND period_label = ? AND status IN ('Pending','Sent','Overdue','Failed')
         ORDER BY id DESC LIMIT 1",
        [$subscriberId, $period]
    );
    if ($open !== null) {
        return ['error' => null, 'id' => (int) $open['id'], 'number' => (string) $open['invoice_number'], 'reused' => true];
    }
    $year = date('Y');
    $last = db_value(
        'SELECT invoice_number FROM subscription_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1',
        ["INV-{$year}-%"]
    );
    $parts = explode('-', (string) ($last ?? ''));
    $number = sprintf('INV-%s-%04d', $year, ((int) end($parts)) + 1);
    $id = db_exec(
        "INSERT INTO subscription_invoices (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token)
         VALUES (?,?,?,?,?,'Pending',?)",
        [$subscriberId, $number, $sub['plan_amount'], $period, date('Y-m-d'), random_token()]
    );
    return ['error' => null, 'id' => $id, 'number' => $number, 'reused' => false];
}

/** @return array{donation_id: int, email: string}|null Null when the invoice is unknown or not yet paid. */
function paid_subscription_invoice(int $invoiceId): ?array
{
    $row = db_one(
        "SELECT i.linked_donation_id, s.email
         FROM subscription_invoices i JOIN subscribers s ON s.id = i.subscriber_id
         WHERE i.id = ? AND i.status = 'Paid' AND i.linked_donation_id IS NOT NULL",
        [$invoiceId]
    );
    if ($row === null) {
        return null;
    }
    return ['donation_id' => (int) $row['linked_donation_id'], 'email' => trim((string) ($row['email'] ?? ''))];
}

/**
 * Issues the donation receipt for a paid subscription invoice.
 *
 * @return array{error: ?string, number: string, created: bool}
 */
function issue_subscription_receipt(int $invoiceId, ?int $userId): array
{
    $paid = paid_subscription_invoice($invoiceId);
    if ($paid === null) {
        return ['error' => 'A receipt is made only after the invoice is paid.', 'number' => '', 'created' => false];
    }
    $issued = issue_donation_receipt($paid['donation_id'], $userId);
    return ['error' => null] + $issued;
}

/**
 * Emails the receipt of a paid invoice to the subscriber, or to the devotee email when the subscriber has none.
 *
 * @param array<string, string> $settings Messaging settings.
 * @return array{error: ?string, email: string, number: string}
 */
function send_subscription_receipt(int $invoiceId, array $settings): array
{
    $paid = paid_subscription_invoice($invoiceId);
    if ($paid === null) {
        return ['error' => 'A receipt is sent only after the invoice is paid.', 'email' => '', 'number' => ''];
    }
    return send_donation_receipt($paid['donation_id'], $settings, $paid['email'] !== '' ? $paid['email'] : null);
}

function subscription_request_block_reason(?string $email, bool $smtpReady): ?string
{
    if ($email === null || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
        return 'This subscriber has no email address, so no payment request was emailed.';
    }
    if (!$smtpReady) {
        return 'Outgoing mail is not set up under Settings, so no payment request was emailed.';
    }
    return null;
}

/**
 * The payment request emailed to a subscriber for one invoice.
 *
 * @param array<string, mixed> $invoice Invoice joined with the subscriber's name, plan_name, and frequency.
 * @return array{subject: string, text: string, html: string}
 */
function subscription_request_email(array $invoice, string $payUrl): array
{
    $name = (string) ($invoice['name'] ?? '');
    $number = (string) ($invoice['invoice_number'] ?? '');
    $period = (string) ($invoice['period_label'] ?? '');
    $due = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($invoice['due_date'] ?? ''));
    $details = [
        'Seva plan' => (string) ($invoice['plan_name'] ?? ''),
        'Billing cycle' => (string) ($invoice['frequency'] ?? ''),
        'Period' => $period,
        'Amount' => '₹' . number_format((float) ($invoice['amount'] ?? 0), 0),
        'Due date' => $due instanceof DateTimeImmutable ? $due->format('j M Y') : '',
        'Invoice' => $number,
    ];
    $temple = app_display_name();
    $intro = "Your seva contribution for {$period} is due. Thank you for supporting {$temple}.";
    $outro = 'Open the link to pay or donate online. If you have already paid, please ignore this message.';

    $lines = ["Namaskar {$name},", '', $intro, ''];
    foreach ($details as $label => $value) {
        $lines[] = "{$label}: {$value}";
    }
    array_push($lines, '', "Pay or donate: {$payUrl}", '', $outro);

    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rows = '';
    foreach ($details as $label => $value) {
        $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b625a;">' . $h($label) . '</td>'
            . '<td style="padding:6px 0;font-weight:700;">' . $h($value) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><body style="margin:0;padding:16px;background:#f3f0ea;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e6dcc8;border-radius:12px;">'
        . '<tr><td style="padding:24px;font-family:Georgia,serif;color:#222222;font-size:15px;line-height:1.5;">'
        . '<p style="margin:0 0 12px;">Namaskar ' . $h($name) . ',</p>'
        . '<p style="margin:0 0 16px;">' . $h($intro) . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">' . $rows . '</table>'
        . '<p style="margin:0 0 20px;"><a href="' . $h($payUrl) . '" style="display:inline-block;background:#7A1626;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;">Pay or donate ' . $h($details['Amount']) . '</a></p>'
        . '<p style="margin:0 0 4px;font-size:13px;color:#6b625a;">' . $h($outro) . '</p>'
        . '<p style="margin:0;font-size:12px;color:#6b625a;word-break:break-all;">' . $h($payUrl) . '</p>'
        . email_signature_html(brand_logo_email_image() !== null)
        . '</td></tr></table></td></tr></table></body></html>';

    return [
        'subject' => "Seva contribution request — {$period} ({$number})",
        'text' => implode("\n", $lines),
        'html' => $html,
    ];
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
