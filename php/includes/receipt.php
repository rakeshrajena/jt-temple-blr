<?php
declare(strict_types=1);

function receipt_number_is_valid(string $number): bool
{
    return preg_match('/^RCPT-\d{10}-\d{4,6}$/', $number) === 1
        || preg_match('/^RCPT-\d{4}-\d{4}$/', $number) === 1;
}

function receipt_serial_value(string $number): int
{
    if (preg_match('/^RCPT-\d{10}-(\d{4,6})$/', $number, $match) === 1) {
        return (int) $match[1];
    }
    if (preg_match('/^RCPT-\d{4}-(\d{4})$/', $number, $match) === 1) {
        return (int) $match[1];
    }
    return 0;
}

function next_receipt_number(PDO $pdo): string
{
    db_mutex($pdo, 'receipt_number');
    $statement = $pdo->prepare("SELECT receipt_number FROM donations WHERE receipt_number LIKE 'RCPT-%'");
    $statement->execute();
    $highest = 0;
    foreach ($statement->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $highest = max($highest, receipt_serial_value((string) ($row['receipt_number'] ?? '')));
    }
    return sprintf('RCPT-%d-%04d', time(), $highest + 1);
}

const RECEIPT_PAGE_WIDTH = 419.53;
const RECEIPT_PAGE_HEIGHT = 595.28;
const RECEIPT_LABEL_X = 40.0;
const RECEIPT_VALUE_X = 136.0;
/** Right edge for text; the gold border is at 396.83. */
const RECEIPT_CONTENT_RIGHT = RECEIPT_PAGE_WIDTH - 40.0;
const RECEIPT_LINE_FACTOR = 1.3;
const RECEIPT_ROW_FACTOR = 1.9;
const RECEIPT_FIELD_SIZES = [9.5, 9.0, 8.5, 8.0, 7.5, 7.0];

/**
 * Wraps each receipt value inside the border and keeps the block above $bottom,
 * shrinking the text first and shortening values only when the smallest size still does not fit.
 *
 * @param list<array{0: string, 1: string}> $fields Label and value pairs.
 * @return array{size: float, rows: list<array{label: string, lines: list<string>, y: float}>, end: float}
 */
function receipt_field_layout(PdfDocument $pdf, array $fields, float $top, float $bottom): array
{
    $width = RECEIPT_CONTENT_RIGHT - RECEIPT_VALUE_X;
    $build = static function (float $size, ?int $maxLines) use ($pdf, $fields, $top, $width): array {
        $rows = [];
        $y = $top;
        foreach ($fields as [$label, $value]) {
            $lines = $pdf->wrap($value, $size, $width);
            if ($lines === []) {
                $lines = [''];
            }
            if ($maxLines !== null && count($lines) > $maxLines) {
                $lines = array_slice($lines, 0, $maxLines);
                $last = $lines[$maxLines - 1];
                while ($last !== '' && $pdf->textWidth($last . '...', $size) > $width) {
                    $last = mb_substr($last, 0, -1);
                }
                $lines[$maxLines - 1] = rtrim($last) . '...';
            }
            $rows[] = ['label' => $label, 'lines' => $lines, 'y' => $y];
            $y -= (count($lines) - 1) * $size * RECEIPT_LINE_FACTOR + $size * RECEIPT_ROW_FACTOR;
        }
        return ['size' => $size, 'rows' => $rows, 'end' => $y];
    };
    foreach (RECEIPT_FIELD_SIZES as $size) {
        $layout = $build($size, null);
        if ($layout['end'] >= $bottom) {
            return $layout;
        }
    }
    $smallest = RECEIPT_FIELD_SIZES[count(RECEIPT_FIELD_SIZES) - 1];
    for ($maxLines = 4; $maxLines > 1; $maxLines--) {
        $layout = $build($smallest, $maxLines);
        if ($layout['end'] >= $bottom) {
            return $layout;
        }
    }
    return $build($smallest, 1);
}

function generate_receipt_pdf(array $donation, array $donor, string $receiptNumber): string
{
    $pageW = RECEIPT_PAGE_WIDTH;
    $pageH = RECEIPT_PAGE_HEIGHT;
    $pdf = new PdfDocument($pageW, $pageH);
    $navy = [122 / 255, 22 / 255, 38 / 255];
    $gold = [201 / 255, 138 / 255, 43 / 255];
    $dark = [0.133, 0.133, 0.133];
    $grey = [0.353, 0.384, 0.439];
    $pdf->setFill(1, 1, 1);
    $pdf->rect(0, 0, $pageW, $pageH, false, true);
    $logo = brand_logo_raster();
    $image = $logo !== null ? $pdf->addImage($logo) : null;
    if ($image !== null && $logo !== null) {
        [$markW, $markH] = brand_fit_box((int) $logo['width'], (int) $logo['height'], 210.0);
        $opacity = brand_watermark_opacity('receipt');
        if ($opacity > 0.0) {
            $pdf->drawImage($image, ($pageW - $markW) / 2, ($pageH - $markH) / 2, $markW, $markH, $opacity);
        }
    }

    $pdf->setStroke(...$gold);
    $pdf->setLineWidth(2);
    $pdf->rect(22.7, 22.7, $pageW - 45.4, $pageH - 45.4);

    $nameY = 530.0;
    if ($image !== null && $logo !== null) {
        [$logoW, $logoH] = brand_fit_box((int) $logo['width'], (int) $logo['height'], 58.0);
        $pdf->drawImage($image, ($pageW - $logoW) / 2, 492, $logoW, $logoH, 1);
        $nameY = 474.0;
    }
    $pdf->setFill(...$navy);
    $temple = app_display_name();
    foreach ($pdf->wrap($temple, 15, 330, true) as $line) {
        $pdf->text(($pageW - $pdf->textWidth($line, 15, true)) / 2, $nameY, $line, 15, 'F2');
        $nameY -= 18;
    }
    $pdf->setFill(...$grey);
    $placeY = $nameY - 2;
    foreach ($pdf->wrap(app_place(), 9, 330, false) as $line) {
        $pdf->text(($pageW - $pdf->textWidth($line, 9)) / 2, $placeY, $line, 9, 'F1');
        $placeY -= 12;
    }
    $pdf->setFill(...$gold);
    $title = 'DONATION RECEIPT';
    $titleY = $placeY - 8;
    $pdf->text(($pageW - $pdf->textWidth($title, 12, true)) / 2, $titleY, $title, 12, 'F2');

    $pdf->setStroke(...$gold);
    $pdf->setLineWidth(0.7);
    $pdf->line(40, $titleY - 10, $pageW - 40, $titleY - 10);

    $y = $titleY - 30;
    $pdf->setFill(...$dark);
    $fields = [
        ['Receipt No.', $receiptNumber],
        ['Date', (string) $donation['donation_date']],
        ['Donor Name', (string) $donor['name']],
    ];
    if (!empty($donor['phone'])) {
        $fields[] = ['Phone', (string) $donor['phone']];
    }
    if (!empty($donor['pan_number'])) {
        $fields[] = ['PAN', (string) $donor['pan_number']];
    }
    $fields[] = ['Donation Type', (string) $donation['donation_type']];
    if ($donation['amount'] !== null && $donation['amount'] !== '') {
        $fields[] = ['Amount', 'Rs. ' . number_format((float) $donation['amount'], 2)];
    }
    $fields[] = ['Purpose', (string) ($donation['purpose'] ?: 'General')];
    $fields[] = ['Payment Mode', (string) $donation['payment_mode']];

    $qrSize = 78.0;
    $qrY = 46.0;
    $signatureRoom = 34.0;
    $bottom = ($qrY + $qrSize + 26.0) + $signatureRoom;
    $layout = receipt_field_layout($pdf, $fields, $y, $bottom);
    $size = $layout['size'];
    foreach ($layout['rows'] as $row) {
        $pdf->text(RECEIPT_LABEL_X, $row['y'], $row['label'] . ':', $size, 'F2');
        foreach ($row['lines'] as $index => $line) {
            $pdf->text(RECEIPT_VALUE_X, $row['y'] - $index * $size * RECEIPT_LINE_FACTOR, $line, $size, 'F1');
        }
    }
    $y = $layout['end'];

    $y -= 6;
    $pdf->setStroke(0.867, 0.867, 0.867);
    $pdf->setLineWidth(0.6);
    $pdf->line(40, $y, $pageW - 40, $y);
    $y -= 28;
    $pdf->setFill(...$dark);
    $pdf->text(40, $y, 'Authorized Signatory: ______________________', 9, 'F1');

    $token = (string) ($donation['receipt_share_token'] ?? '');
    $link = receipt_public_url($token);
    $symbol = $link === '' ? [] : qr_matrix($link);
    $qrX = $pageW - 40 - $qrSize;
    if ($symbol !== []) {
        $pdf->setFill(1, 1, 1);
        $pdf->rect($qrX - 4, $qrY - 4, $qrSize + 8, $qrSize + 8, false, true);
        $pdf->matrix($qrX, $qrY, $qrSize, $symbol);
        $pdf->setFill(...$grey);
        $caption = 'Scan to view this gift';
        $pdf->text($qrX + ($qrSize - $pdf->textWidth($caption, 7)) / 2, $qrY + $qrSize + 8, $caption, 7, 'F1');
    }

    $note = receipt_closing_note();
    $mark = receipt_namaste_mark();
    $markSize = 10.0;
    $column = $symbol === [] ? 340.0 : ($qrX - 14.0 - 40.0);
    $textMax = $mark === null ? $column : max(80.0, $column - $markSize - 3.0);
    $lines = $pdf->wrap($note, 8.5, $textMax);
    $lineGap = 11.0;
    $count = count($lines);
    $startY = $symbol === []
        ? $y - 22.0
        : $qrY + ($qrSize / 2) + ((($count - 1) * $lineGap) / 2);
    $pdf->setFill(...$grey);
    $last = $count - 1;
    foreach ($lines as $index => $line) {
        $lineY = $startY - ($index * $lineGap);
        $pdf->text(40, $lineY, $line, 8.5, 'F3');
        if ($index === $last && $mark !== null) {
            $pdf->drawImage(
                $pdf->addImage($mark),
                40 + $pdf->textWidth($line, 8.5) + 3,
                $lineY - 1.5,
                $markSize,
                $markSize,
                1
            );
        }
    }

    $path = APP_ROOT . '/storage/receipts/' . $receiptNumber . '.pdf';
    $pdf->save($path);
    return $path;
}

function receipt_share_message(array $row, string $link = ''): string
{
    $name = trim((string) ($row['donor_name'] ?? ''));
    $amount = ($row['amount'] ?? null) === null || $row['amount'] === ''
        ? 'an in-kind gift'
        : 'Rs.' . number_format((float) $row['amount'], 0);
    $purpose = trim((string) ($row['purpose'] ?? ''));
    $purpose = $purpose !== '' ? $purpose : 'General';
    $mode = trim((string) ($row['payment_mode'] ?? ''));
    $modeText = $mode !== '' ? ', ' . $mode : '';
    $text = 'Namaskar ' . $name . ', your receipt ' . (string) $row['receipt_number']
        . ' dated ' . (string) $row['donation_date'] . ' for ' . $amount
        . ' (' . $purpose . $modeText . ') is ready.';
    $link = trim($link);
    if ($link !== '') {
        return $text . "\nReceipt: " . $link . "\n— " . app_display_name();
    }
    return $text . ' — ' . app_display_name();
}

function receipt_public_thanks(): string
{
    return 'Thank you for your donation and devotion toward Lord Jagannath.';
}

function receipt_closing_note(): string
{
    return 'Thank you for your generous contribution towards Mahaprasad and temple seva. This receipt is issued for your records. '
        . receipt_public_thanks();
}

/** @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null */
function receipt_namaste_mark(): ?array
{
    $path = APP_ROOT . '/static/namaste.png';
    return is_file($path) ? brand_decode_png($path) : null;
}

function receipt_public_url(string $token): string
{
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return '';
    }
    return app_public_origin() . app_web_script() . '?' . http_build_query(['r' => 'receipts/open/' . $token]);
}

function receipt_ensure_share_token(int $donationId): string
{
    $existing = db_value('SELECT receipt_share_token FROM donations WHERE id = ?', [$donationId]);
    if (is_string($existing) && preg_match('/^[a-f0-9]{32}$/', $existing) === 1) {
        return $existing;
    }
    $token = bin2hex(random_bytes(16));
    db_exec(
        'UPDATE donations SET receipt_share_token = ? WHERE id = ? AND (receipt_share_token IS NULL OR receipt_share_token = \'\')',
        [$token, $donationId]
    );
    $saved = db_value('SELECT receipt_share_token FROM donations WHERE id = ?', [$donationId]);
    return is_string($saved) && $saved !== '' ? $saved : $token;
}

/** @return array<string, mixed>|null */
function receipt_public_row(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return null;
    }
    return db_one(
        "SELECT d.receipt_number, d.donation_date, d.amount, d.donation_type, d.purpose,
                d.payment_mode, d.receipt_cancelled, don.name AS donor_name
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         WHERE d.receipt_share_token = ?
           AND d.receipt_generated = 1
           AND d.receipt_number IS NOT NULL
           AND d.receipt_number <> ''",
        [$token]
    );
}

function receipt_email_body(array $row, string $link = ''): string
{
    return receipt_share_message($row, $link) . "\n\nThe receipt PDF is attached.";
}

function receipt_bulk_email_body(string $message, string $link): string
{
    $text = trim($message);
    if (trim($link) !== '') {
        $text .= "\n\nReceipt: " . trim($link);
    }
    return $text . "\n\nThe receipt PDF is attached.";
}

function ensure_receipt_share_schema(PDO $pdo): void
{
    ensure_column($pdo, 'donations', 'receipt_share_token', 'VARCHAR(64) NULL');
    $index = $pdo->query("SHOW INDEX FROM donations WHERE Key_name = 'uq_receipt_share_token'")->fetch();
    if ($index === false) {
        $pdo->exec('ALTER TABLE donations ADD UNIQUE KEY uq_receipt_share_token (receipt_share_token)');
    }
    $ids = $pdo->query(
        "SELECT id FROM donations
         WHERE receipt_generated = 1
           AND receipt_number IS NOT NULL
           AND receipt_number <> ''
           AND (receipt_share_token IS NULL OR receipt_share_token = '')"
    )->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare(
        'UPDATE donations SET receipt_share_token = ? WHERE id = ? AND (receipt_share_token IS NULL OR receipt_share_token = \'\')'
    );
    foreach ($ids as $id) {
        $update->execute([bin2hex(random_bytes(16)), (int) $id]);
    }
    rebuild_public_receipt_pdfs($pdo);
}

function rebuild_public_receipt_pdfs(PDO $pdo): void
{
    $flag = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'receipt_public_qr'")->fetchColumn();
    if ($flag !== false && (string) $flag === '4') {
        return;
    }
    $rows = $pdo->query(
        "SELECT d.*, don.name, don.phone, don.email, don.address, don.pan_number
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         WHERE d.receipt_generated = 1 AND d.receipt_number IS NOT NULL AND d.receipt_number <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $path = APP_ROOT . '/storage/receipts/' . $row['receipt_number'] . '.pdf';
        if (!is_file($path)) {
            continue;
        }
        try {
            generate_receipt_pdf($row, $row, (string) $row['receipt_number']);
        } catch (Throwable $e) {
            error_log('[jt_blr] receipt qr: ' . $e->getMessage());
        }
    }
    brand_upsert('receipt_public_qr', '4');
}

/**
 * Writes the receipt PDF for a donation, giving it the next receipt number the first time.
 *
 * @return array{number: string, created: bool}
 */
function issue_donation_receipt(int $donationId, ?int $userId): array
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $donation = db_one('SELECT * FROM donations WHERE id = ? FOR UPDATE', [$donationId]);
        if ($donation === null) {
            throw new RuntimeException('Donation not found.');
        }
        $donor = db_one('SELECT * FROM donors WHERE id = ?', [(int) $donation['donor_id']]);
        if ($donor === null) {
            throw new RuntimeException('Donor not found.');
        }
        $existing = trim((string) ($donation['receipt_number'] ?? ''));
        $receiptNumber = $existing !== '' ? $existing : next_receipt_number($pdo);
        $donation['receipt_share_token'] = receipt_ensure_share_token($donationId);
        generate_receipt_pdf($donation, $donor, $receiptNumber);
        if ($existing === '') {
            $marked = $pdo->prepare(
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
            [$donationId, $receiptNumber, $userId]
        );
        if ($own) {
            $pdo->commit();
        }
        return ['number' => $receiptNumber, 'created' => $existing === ''];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Emails a generated receipt PDF with the public receipt link.
 *
 * @param array<string, string> $settings Messaging settings.
 * @param ?string $to Send here instead of the devotee email saved on the donation.
 * @return array{error: ?string, email: string, number: string}
 */
function send_donation_receipt(int $donationId, array $settings, ?string $to = null): array
{
    $row = receipt_rows($donationId)[0] ?? null;
    if ($row === null) {
        return ['error' => 'Receipt not found.', 'email' => '', 'number' => ''];
    }
    $email = trim($to ?? (string) ($row['donor_email'] ?? ''));
    $number = (string) $row['receipt_number'];
    $result = ['error' => null, 'email' => $email, 'number' => $number];
    $blocked = receipt_email_block_reason($email, receipt_file_exists($number), smtp_is_ready($settings));
    if ($blocked !== null) {
        return ['error' => $blocked] + $result;
    }
    $pdf = file_get_contents(receipt_path($number));
    if ($pdf === false) {
        return ['error' => 'The receipt PDF is not ready to send.'] + $result;
    }
    $subject = 'Receipt ' . $number;
    $error = send_smtp_message($settings, $email, $subject, receipt_email_body($row, receipt_public_url((string) ($row['receipt_share_token'] ?? ''))), [
        'filename' => $number . '.pdf',
        'content' => $pdf,
        'mime' => 'application/pdf',
    ]);
    notify_log('EMAIL', $email, $error === null ? 'Sent. ' . $subject : 'Not sent. ' . $subject . ' ' . $error);
    return ['error' => $error] + $result;
}

function receipt_email_block_reason(string $email, bool $pdfReady, bool $smtpReady): ?string
{
    if (trim($email) === '') {
        return 'This devotee has no email address.';
    }
    if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
        return 'The devotee email address is not valid.';
    }
    if (!$pdfReady) {
        return 'The receipt PDF is not ready to send.';
    }
    if (!$smtpReady) {
        return 'Outgoing mail is not configured.';
    }
    return null;
}

function backfill_receipt_pdfs(PDO $pdo): void
{
    $rows = $pdo->query(
        "SELECT d.*, don.name, don.phone, don.email, don.address, don.pan_number
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         WHERE d.receipt_generated = 1 AND d.receipt_number IS NOT NULL AND d.receipt_number <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $path = APP_ROOT . '/storage/receipts/' . $row['receipt_number'] . '.pdf';
        if (!is_file($path)) {
            generate_receipt_pdf($row, $row, (string) $row['receipt_number']);
        }
    }
}
