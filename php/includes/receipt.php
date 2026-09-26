<?php
declare(strict_types=1);

function next_receipt_number(PDO $pdo): string
{
    $year = date('Y');
    $row = db_one(
        'SELECT receipt_number FROM donations WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1',
        ["RCPT-{$year}-%"]
    );
    $seq = 1;
    if ($row !== null && !empty($row['receipt_number'])) {
        $parts = explode('-', (string) $row['receipt_number']);
        $seq = ((int) end($parts)) + 1;
    }
    return sprintf('RCPT-%s-%04d', $year, $seq);
}

function generate_receipt_pdf(array $donation, array $donor, string $receiptNumber): string
{
    $pdf = new PdfDocument(419.53, 595.28);
    $navy = [122 / 255, 22 / 255, 38 / 255];
    $gold = [201 / 255, 138 / 255, 43 / 255];
    $dark = [0.133, 0.133, 0.133];
    $grey = [0.353, 0.384, 0.439];

    $pdf->setStroke(...$gold);
    $pdf->setLineWidth(2);
    $pdf->rect(22.7, 22.7, 419.53 - 45.4, 595.28 - 45.4);

    $pdf->setFill(...$navy);
    $nameY = 530.0;
    $pdf->text($pdf->textWidth('SHREE JAGANNATH TEMPLE', 16, true) > 0
        ? (419.53 - $pdf->textWidth('SHREE JAGANNATH TEMPLE', 16, true)) / 2
        : 40, $nameY, 'SHREE JAGANNATH TEMPLE', 16, 'F2');
    $pdf->setFill(...$grey);
    $place = APP_PLACE;
    $pdf->text((419.53 - $pdf->textWidth($place, 9)) / 2, $nameY - 14, $place, 9, 'F1');
    $pdf->setFill(...$gold);
    $title = 'DONATION RECEIPT';
    $pdf->text((419.53 - $pdf->textWidth($title, 12, true)) / 2, $nameY - 34, $title, 12, 'F2');

    $pdf->setStroke(...$gold);
    $pdf->setLineWidth(0.7);
    $pdf->line(40, $nameY - 44, 419.53 - 40, $nameY - 44);

    $y = $nameY - 68;
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

    foreach ($fields as [$label, $value]) {
        $pdf->text(40, $y, $label . ':', 9.5, 'F2');
        $pdf->text(136, $y, $value, 9.5, 'F1');
        $y -= 18;
    }

    $y -= 6;
    $pdf->setStroke(0.867, 0.867, 0.867);
    $pdf->setLineWidth(0.6);
    $pdf->line(40, $y, 419.53 - 40, $y);
    $y -= 20;

    $pdf->setFill(...$grey);
    $note = 'Thank you for your generous contribution towards Mahaprasad and temple seva. This receipt is issued for your records.';
    foreach ($pdf->wrap($note, 8.5, 340) as $line) {
        $pdf->text(40, $y, $line, 8.5, 'F3');
        $y -= 12;
    }
    $y -= 24;
    $pdf->setFill(...$dark);
    $pdf->text(40, $y, 'Authorized Signatory: ______________________', 9, 'F1');

    $path = APP_ROOT . '/storage/receipts/' . $receiptNumber . '.pdf';
    $pdf->save($path);
    return $path;
}

function backfill_receipt_pdfs(): void
{
    $rows = db_all(
        "SELECT d.*, don.name, don.phone, don.email, don.address, don.pan_number
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         WHERE d.receipt_generated = 1 AND d.receipt_number IS NOT NULL AND d.receipt_number <> ''"
    );
    foreach ($rows as $row) {
        $path = APP_ROOT . '/storage/receipts/' . $row['receipt_number'] . '.pdf';
        if (!is_file($path)) {
            generate_receipt_pdf($row, $row, (string) $row['receipt_number']);
        }
    }
}
