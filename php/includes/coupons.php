<?php
declare(strict_types=1);

function generate_coupon_batch_pdf(
    int $batchId,
    string $couponName,
    float $cost,
    int $startSlNo,
    int $quantity
): string {
    $pageW = 841.89;
    $pageH = 595.28;
    $cols = 4;
    $rows = 4;
    $marginX = 18.0;
    $marginY = 16.0;
    $cellW = ($pageW - (2 * $marginX)) / $cols;
    $cellH = ($pageH - (2 * $marginY)) / $rows;
    $perPage = $cols * $rows;

    $pdf = new PdfDocument($pageW, $pageH);
    $navy = [17 / 255, 44 / 255, 97 / 255];

    for ($i = 0; $i < $quantity; $i++) {
        if ($i > 0 && $i % $perPage === 0) {
            $pdf->addPage();
        }
        $slot = $i % $perPage;
        $col = $slot % $cols;
        $row = intdiv($slot, $cols);
        $x = $marginX + ($col * $cellW) + 4;
        $y = $pageH - $marginY - (($row + 1) * $cellH) + 6;
        $w = $cellW - 8;
        $h = $cellH - 10;
        draw_coupon($pdf, $x, $y, $w, $h, $couponName, $cost, $startSlNo + $i, $navy);
    }

    $path = APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
    $pdf->save($path);
    return $path;
}

/** @param array{0:float,1:float,2:float} $navy */
function draw_coupon(
    PdfDocument $pdf,
    float $x,
    float $y,
    float $w,
    float $h,
    string $couponName,
    float $cost,
    int $slNo,
    array $navy
): void {
    $pdf->setStroke(...$navy);
    $pdf->setFill(...$navy);
    $pdf->setLineWidth(1.4);
    $pdf->setDash();
    $pdf->rect($x, $y, $w, $h);

    $perfX = $x + ($w * 0.78);
    $pdf->setDash(1.2, 2.2);
    $pdf->line($perfX, $y + 8, $perfX, $y + $h - 8);
    $pdf->setDash();

    $title = strtoupper($couponName);
    $words = preg_split('/\s+/', $title) ?: [$title];
    $line1 = $title;
    $line2 = '';
    if (strlen($title) > 16 && count($words) > 1) {
        $mid = intdiv(count($words), 2);
        $line1 = implode(' ', array_slice($words, 0, $mid));
        $line2 = implode(' ', array_slice($words, $mid));
    }
    $pdf->text($x + 8, $y + $h - 22, $line1, 11, 'F2');
    $ruleY = $y + $h - 28;
    if ($line2 !== '') {
        $pdf->text($x + 8, $y + $h - 36, $line2, 11, 'F2');
        $ruleY = $y + $h - 42;
    }
    $pdf->setLineWidth(0.7);
    $pdf->line($x + 8, $ruleY, $perfX - 8, $ruleY);

    $amount = 'Rs. ' . number_format($cost, 0) . '/-';
    $pdf->text($x + 8, $ruleY - 22, $amount, 14, 'F2');

    $sl = 'SL NO : ' . $slNo;
    $slWidth = $pdf->textWidth($sl, 8, true);
    $pdf->text($perfX - 8 - $slWidth, $ruleY - 20, $sl, 8, 'F2');
}
