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

$pdf = new PdfDocument(RECEIPT_PAGE_WIDTH, RECEIPT_PAGE_HEIGHT);

$long = str_repeat('A', 120);
$lines = $pdf->wrap('Ref ' . $long . ' end', 9.5, 100);
$widest = max(array_map(static fn (string $line): float => $pdf->textWidth($line, 9.5), $lines));
check($widest <= 100, 'a single word longer than the line is broken to fit');
check(implode('', array_map(static fn (string $l): string => str_replace(' ', '', $l), $lines)) === 'Ref' . $long . 'end', 'breaking a long word keeps every character');
check($pdf->wrap('Monthly Seva', 9.5, 200) === ['Monthly Seva'], 'short text stays on one line');
check(PdfDocument::plainText('Subscription — Seva (Sept–Oct)') === 'Subscription - Seva (Sept-Oct)', 'a dash prints as a hyphen, not ???');
check(PdfDocument::plainText('₹501 “Annadan” ‘seva’ …') === 'Rs.501 "Annadan" \'seva\' ...', 'rupee sign, quotes, and ellipsis print as plain text');
check(PdfDocument::plainText('ଜଗନ୍ନାଥ') === str_repeat('?', mb_strlen('ଜଗନ୍ନାଥ')), 'other letters print as one ? each, not one per byte');
check(abs($pdf->textWidth('A — B', 9.5) - $pdf->textWidth('A - B', 9.5)) < 0.01, 'widths are measured on the text that is printed');

/**
 * @param array{size: float, rows: list<array{label: string, lines: list<string>, y: float}>, end: float} $layout
 */
function layout_fits(PdfDocument $pdf, array $layout, float $bottom): bool
{
    foreach ($layout['rows'] as $row) {
        foreach ($row['lines'] as $index => $line) {
            $lineY = $row['y'] - $index * $layout['size'] * RECEIPT_LINE_FACTOR;
            if (RECEIPT_VALUE_X + $pdf->textWidth($line, $layout['size']) > RECEIPT_CONTENT_RIGHT || $lineY < $bottom) {
                return false;
            }
        }
        if (RECEIPT_LABEL_X + $pdf->textWidth($row['label'] . ':', $layout['size'], true) > RECEIPT_VALUE_X - 4) {
            return false;
        }
    }
    return $layout['end'] >= $bottom;
}

$subscription = [
    ['Receipt No.', 'RCPT-1790503414-0001'],
    ['Date', '2026-09-25'],
    ['Donor Name', 'Sujata Nayak'],
    ['Phone', '9437098765'],
    ['Donation Type', 'Cash'],
    ['Amount', 'Rs. 501.00'],
    ['Purpose', 'Subscription — Monthly Mahaprasad Seva (September 2026)'],
    ['Payment Mode', 'UPI'],
];
$layout = receipt_field_layout($pdf, $subscription, 404.0, 184.0);
check(layout_fits($pdf, $layout, 184.0), 'a subscription purpose stays inside the receipt border');
check($layout['size'] === 9.5, 'a normal receipt keeps the usual text size');
$purpose = $layout['rows'][6];
check(count($purpose['lines']) >= 2 && implode(' ', $purpose['lines']) === $subscription[6][1], 'a long purpose wraps onto more lines without losing words');

$crowded = $subscription;
$crowded[2][1] = str_repeat('Venkata Lakshmi Narasimha ', 8);
$crowded[6][1] = str_repeat('Subscription — Quarterly Vastra and Annadaan Seva for the family ', 6);
$crowded[] = ['PAN', 'ABCDE1234F'];
$tight = receipt_field_layout($pdf, $crowded, 404.0, 184.0);
check(layout_fits($pdf, $tight, 184.0), 'very long name and purpose still stay above the signature and QR code');
check($tight['size'] < 9.5, 'the text gets smaller before anything is cut');

$extreme = $crowded;
$extreme[6][1] = str_repeat('word ', 400);
$cut = receipt_field_layout($pdf, $extreme, 404.0, 184.0);
check(layout_fits($pdf, $cut, 184.0), 'text too long for the page is shortened inside the border');
check(str_ends_with(end($cut['rows'][6]['lines']), '...'), 'a shortened value ends with ...');

$path = generate_receipt_pdf(
    ['donation_date' => '2026-09-25', 'donation_type' => 'Cash', 'amount' => '501', 'purpose' => $crowded[6][1], 'payment_mode' => 'UPI', 'receipt_share_token' => str_repeat('ab', 16)],
    ['name' => $crowded[2][1], 'phone' => '9437098765', 'pan_number' => 'ABCDE1234F'],
    'RCPT-LAYOUT-CHECK'
);
check(is_file($path) && filesize($path) > 1000, 'a crowded receipt PDF is still written');
if (is_file($path)) {
    unlink($path);
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
