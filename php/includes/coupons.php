<?php
declare(strict_types=1);

function coupon_request_error(string $name, float $cost, int $quantity): ?string
{
    if (trim($name) === '') {
        return 'Enter a coupon name.';
    }
    if ($cost <= 0) {
        return 'Enter a cost above zero.';
    }
    if ($quantity < 1 || $quantity > 400) {
        return 'Enter a quantity from 1 to 400.';
    }
    return null;
}

function coupon_ranges_overlap(int $startA, int $endA, int $startB, int $endB): bool
{
    return $startA <= $endB && $startB <= $endA;
}

function coupon_pdf_path(int $batchId): string
{
    return APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
}

function forget_coupon_pdf(int $batchId): void
{
    $path = coupon_pdf_path($batchId);
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * @return array{error: ?string, id: ?int, start: ?int, end: ?int, total: ?float}
 */
function create_coupon_batch(string $name, float $cost, int $quantity, int $userId): array
{
    $failed = ['error' => null, 'id' => null, 'start' => null, 'end' => null, 'total' => null];
    $error = coupon_request_error($name, $cost, $quantity);
    if ($error !== null) {
        $failed['error'] = $error;
        return $failed;
    }
    $name = trim($name);
    $cost = round($cost, 2);
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $last = db_value('SELECT MAX(end_sl_no) FROM food_coupon_batches');
        $start = ($last === null || $last === false) ? 1 : ((int) $last) + 1;
        $end = $start + $quantity - 1;
        $total = round($cost * $quantity, 2);
        $id = db_exec(
            'INSERT INTO food_coupon_batches (coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, created_date, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [$name, $cost, $start, $end, $quantity, $total, date('Y-m-d'), $userId]
        );
        record_approval('coupon', $id, 'Waiting', $total, $userId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'id' => $id, 'start' => $start, 'end' => $end, 'total' => $total];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function coupon_serial_clash(int $start, int $end, int $exceptId): ?string
{
    $row = db_one(
        'SELECT start_sl_no, end_sl_no FROM food_coupon_batches WHERE id <> ? AND start_sl_no <= ? AND end_sl_no >= ? LIMIT 1',
        [$exceptId, $end, $start]
    );
    if ($row === null) {
        return null;
    }
    return 'That quantity overlaps serial numbers ' . $row['start_sl_no'] . '–' . $row['end_sl_no'] . '.';
}

/**
 * @return array{error: ?string, changed: bool}
 */
function update_coupon_batch(int $batchId, string $name, float $cost, int $quantity, int $userId): array
{
    $error = coupon_request_error($name, $cost, $quantity);
    if ($error !== null) {
        return ['error' => $error, 'changed' => false];
    }
    $batch = db_one('SELECT id, coupon_name, cost, quantity, start_sl_no FROM food_coupon_batches WHERE id = ?', [$batchId]);
    if ($batch === null) {
        return ['error' => 'That coupon batch was not found.', 'changed' => false];
    }
    if (
        trim($name) === (string) $batch['coupon_name']
        && abs(round($cost, 2) - round((float) $batch['cost'], 2)) < 0.001
        && $quantity === (int) $batch['quantity']
    ) {
        return ['error' => null, 'changed' => false];
    }
    $start = (int) $batch['start_sl_no'];
    $end = $start + $quantity - 1;
    $clash = coupon_serial_clash($start, $end, $batchId);
    if ($clash !== null) {
        return ['error' => $clash, 'changed' => false];
    }
    $total = round($cost * $quantity, 2);
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        db_exec(
            'UPDATE food_coupon_batches SET coupon_name = ?, cost = ?, end_sl_no = ?, quantity = ?, total_value = ? WHERE id = ?',
            [trim($name), round($cost, 2), $end, $quantity, $total, $batchId]
        );
        record_approval('coupon', $batchId, 'Waiting', $total, $userId);
        forget_coupon_pdf($batchId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'changed' => true];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function remove_coupon_batch(int $batchId, string $role): ?string
{
    $row = db_one(
        "SELECT b.id, a.status FROM food_coupon_batches b
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE b.id = ?",
        [$batchId]
    );
    if ($row === null) {
        return 'That coupon batch was not found.';
    }
    if ((string) ($row['status'] ?? '') === 'Approved' && $role !== 'Admin') {
        return 'Only an Admin can remove a batch that has been approved.';
    }
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        db_exec("DELETE FROM approvals WHERE subject_type = 'coupon' AND subject_id = ?", [$batchId]);
        db_exec('DELETE FROM food_coupon_batches WHERE id = ?', [$batchId]);
        forget_coupon_pdf($batchId);
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

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
    $logo = brand_logo_raster();
    $image = $logo !== null ? $pdf->addImage($logo) : null;

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
        draw_coupon($pdf, $x, $y, $w, $h, $couponName, $cost, $startSlNo + $i, $navy, $image);
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
    array $navy,
    ?int $logo = null
): void {
    if ($logo !== null) {
        $mark = min($w, $h) * 0.42;
        $pdf->drawImage($logo, $x + ($w - $mark) / 2, $y + ($h - $mark) / 2, $mark, $mark, 0.14);
    }

    $pdf->setStroke(...$navy);
    $pdf->setFill(...$navy);
    $pdf->setLineWidth(1.4);
    $pdf->setDash();
    $pdf->rect($x, $y, $w, $h);

    $perfX = $x + ($w * 0.78);
    $pdf->setDash(1.2, 2.2);
    $pdf->line($perfX, $y + 8, $perfX, $y + $h - 8);
    $pdf->setDash();

    $brandY = $y + $h - 18;
    $brandX = $x + 8;
    if ($logo !== null) {
        $pdf->drawImage($logo, $x + 8, $brandY - 2, 14, 14, 1);
        $brandX = $x + 26;
    }
    $temple = $pdf->fitText(app_display_name(), 7, $perfX - 8 - $brandX, true);
    $pdf->text($brandX, $brandY, $temple, 7, 'F2');

    $title = strtoupper($couponName);
    $words = preg_split('/\s+/', $title) ?: [$title];
    $line1 = $title;
    $line2 = '';
    if (strlen($title) > 16 && count($words) > 1) {
        $mid = intdiv(count($words), 2);
        $line1 = implode(' ', array_slice($words, 0, $mid));
        $line2 = implode(' ', array_slice($words, $mid));
    }
    $pdf->text($x + 8, $y + $h - 38, $line1, 11, 'F2');
    $ruleY = $y + $h - 44;
    if ($line2 !== '') {
        $pdf->text($x + 8, $y + $h - 52, $line2, 11, 'F2');
        $ruleY = $y + $h - 58;
    }
    $pdf->setLineWidth(0.7);
    $pdf->line($x + 8, $ruleY, $perfX - 8, $ruleY);

    $amount = 'Rs. ' . number_format($cost, 0) . '/-';
    $pdf->text($x + 8, $ruleY - 22, $amount, 14, 'F2');

    $sl = 'SL NO : ' . $slNo;
    $slWidth = $pdf->textWidth($sl, 8, true);
    $pdf->text($perfX - 8 - $slWidth, $ruleY - 20, $sl, 8, 'F2');
}
