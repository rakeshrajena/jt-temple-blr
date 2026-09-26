<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

$row = [
    'donor_name' => 'R Jena',
    'receipt_number' => 'RCPT-2026-0099',
    'donation_date' => '2026-09-26',
    'amount' => '11000.00',
    'purpose' => 'Donation In-Kind',
    'payment_mode' => 'In-Kind',
];
$message = receipt_share_message($row);
check(
    str_contains($message, 'Namaskar R Jena')
    && str_contains($message, 'RCPT-2026-0099')
    && str_contains($message, 'Rs.11,000')
    && str_contains($message, 'Donation In-Kind, In-Kind'),
    'the share text names the devotee, receipt, amount, and in-kind gift'
);
$link = 'http://localhost/jt_blr/jt-temple-blr/php/index.php?r=receipts/open/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$linked = receipt_share_message($row, $link);
check(
    str_contains($linked, "Receipt: {$link}")
    && str_contains(whatsapp_web_url('8328800931', $linked, '91') ?? '', rawurlencode('Receipt: ' . $link)),
    'the WhatsApp message includes the receipt link'
);
check(receipt_public_url('not-a-token') === '', 'a receipt link needs the full token');
check(
    str_contains(receipt_public_url(str_repeat('ab', 16)), 'r=receipts%2Fopen%2F' . str_repeat('ab', 16)),
    'a receipt token becomes a public link'
);
check(
    str_contains(receipt_email_body($row, $link), 'The receipt PDF is attached.')
    && str_contains(receipt_email_body($row, $link), $link),
    'the email attaches the PDF and includes the receipt link'
);
$jena = db_one("SELECT receipt_share_token, receipt_number FROM donations WHERE receipt_number = 'RCPT-2026-0010'");
check(
    $jena !== null && preg_match('/^[a-f0-9]{32}$/', (string) $jena['receipt_share_token']) === 1,
    'an existing receipt has a public link token'
);
$url = whatsapp_web_url('8328800931', $message, '91');
check(
    $url !== null && str_starts_with($url, 'https://web.whatsapp.com/send?phone=918328800931&text='),
    'WhatsApp opens a chat for the devotee phone'
);
check(bulk_compose_error('', 1) !== null, 'a bulk message is required');
check(bulk_compose_error('Namaskar', 0) !== null, 'bulk send needs a selection');
check(bulk_compose_error('Namaskar', 51) !== null, 'bulk send stops at 50 people');
check(bulk_compose_error('Namaskar', 2) === null, 'a short note to a few people is accepted');
$bulkBody = receipt_bulk_email_body('Please find your receipt.', 'http://example.test/receipt');
check(
    str_contains($bulkBody, 'Please find your receipt.')
    && str_contains($bulkBody, 'Receipt: http://example.test/receipt')
    && str_contains($bulkBody, 'The receipt PDF is attached.'),
    'each receipt email keeps the typed note, the link, and the PDF'
);
[$bulkCategory, $bulkText] = bulk_result_flash(0, 2, 0);
check($bulkCategory === 'error' && str_contains($bulkText, 'valid email'), 'a bulk email with no valid address is not treated as sent');
[$bulkCategory, $bulkText] = bulk_result_flash(1, 1, 0);
check($bulkCategory === 'success' && str_contains($bulkText, 'Emailed 1 person.') && str_contains($bulkText, '1 skipped'), 'a bulk email reports who was mailed and who was skipped');
check(receipt_email_block_reason('', true, true) === 'This devotee has no email address.', 'a receipt without an email is not mailed');
check(receipt_email_block_reason('asrrjprince.com', true, true) === 'The devotee email address is not valid.', 'a domain without @ is not a mailbox');
check(receipt_email_block_reason('devotee@example.com', false, true) === 'The receipt PDF is not ready to send.', 'a missing PDF is not mailed');
check(receipt_email_block_reason('devotee@example.com', true, false) === 'Outgoing mail is not configured.', 'mail waits until the server is saved');
check(receipt_email_block_reason('devotee@example.com', true, true) === null, 'a complete receipt can be emailed');

$plain = smtp_data_payload('Temple', 'seva@temple.test', 'devotee@example.com', 'Hello', "Line\n.secret");
check(
    str_contains($plain, 'Content-Type: text/plain')
    && str_contains($plain, '..secret')
    && !str_contains($plain, 'multipart/mixed'),
    'a message without a file stays plain text and escapes a leading dot'
);
$pdf = "%PDF-1.4\nreceipt";
$attached = smtp_data_payload('Temple', 'seva@temple.test', 'devotee@example.com', 'Receipt RCPT-2026-0099', 'Body', [
    'filename' => 'RCPT-2026-0099.pdf',
    'content' => $pdf,
    'mime' => 'application/pdf',
]);
check(
    str_contains($attached, 'multipart/mixed')
    && str_contains($attached, 'filename="RCPT-2026-0099.pdf"')
    && str_contains($attached, chunk_split(base64_encode($pdf), 76, "\r\n")),
    'the receipt email attaches the PDF'
);
check(
    smtp_attachment_error(['filename' => '../secret.pdf', 'content' => 'x', 'mime' => 'application/pdf']) !== null,
    'a receipt file name cannot leave the receipts folder'
);

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all receipt share tests passed\n";
