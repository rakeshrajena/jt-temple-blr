<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\View;
use SJT\Repo\Bookings;
use SJT\Repo\Settings;

/** Builds the invoice as HTML (print view) and as a PDF from the same data. */
final class InvoiceRenderer
{
    public function __construct(private Bookings $bookings, private Settings $settings, private UpiPayment $upi)
    {
    }

    public function data(array $b): array
    {
        $id = (int) $b['id'];
        $verified = array_values(array_filter($this->bookings->payments($id), fn ($p) => in_array($p['status'], ['verified', 'refunded'], true)));
        $qr = null;
        if (in_array($b['payment_status'], ['pending', 'failed'], true) && in_array($b['booking_status'], ['pending', 'confirmed'], true) && $this->upi->available()) {
            $png = $this->upi->bookingQrPng($b);
            $qr = $png ? 'data:image/png;base64,' . base64_encode($png) : null;
        }
        return [
            'b' => $b,
            'items' => $this->bookings->items($id),
            'payment' => $verified[0] ?? null,
            'temple' => [
                'name' => $this->settings->get('temple_name', 'Shree Jagannatha Temple Bengaluru'),
                'address' => $this->settings->get('temple_address', ''),
                'phone' => $this->settings->get('temple_phone', ''),
                'email' => $this->settings->get('temple_email', ''),
                'website' => $this->settings->get('temple_website', ''),
                'gstin' => $this->settings->get('temple_gstin', ''),
            ],
            'upi_vpa' => $this->upi->mode() === 'dynamic' ? $this->settings->get('upi_vpa') : null,
            'qr' => $qr,
            'terms' => $this->settings->get('terms_text', ''),
        ];
    }

    public function html(array $b, bool $forPdf = false): string
    {
        return View::render('invoice', $this->data($b) + ['forPdf' => $forPdf], null);
    }

    public function pdf(array $b): string
    {
        return $this->renderPdf($this->data($b));
    }

    private function renderPdf(array $d): string
    {
        $b = $d['b'];
        $items = $d['items'];
        $temple = $d['temple'];
        $payment = $d['payment'];
        $terms = (string) $d['terms'];

        $pageW = 595.28;
        $pageH = 841.89;
        $pdf = new \PdfDocument($pageW, $pageH);

        $navy = [0.32, 0.06, 0.06];
        $grey = [0.38, 0.34, 0.30];
        $dark = [0.16, 0.11, 0.07];

        $left = 50.0;
        $right = $pageW - 50.0;
        $width = $right - $left;

        // ---- header ----
        $y = $pageH - 55.0;
        $pdf->setFill(...$navy);
        $pdf->text($left, $y, $temple['name'], 16, 'F2');
        $y -= 20;
        $pdf->setFill(...$grey);
        $headerLines = [];
        if ($temple['address']) {
            $headerLines[] = $temple['address'];
        }
        if ($temple['phone']) {
            $headerLines[] = 'Phone: ' . $temple['phone'];
        }
        if ($temple['email']) {
            $headerLines[] = 'Email: ' . $temple['email'];
        }
        if ($temple['website']) {
            $headerLines[] = preg_replace('#^https?://#', '', $temple['website']);
        }
        if ($temple['gstin']) {
            $headerLines[] = 'GSTIN: ' . $temple['gstin'];
        }
        foreach ($headerLines as $line) {
            $pdf->text($left, $y, $line, 9);
            $y -= 13;
        }

        // ---- right column: INVOICE + meta ----
        $rx = $left + $width * 0.55;
        $ry = $pageH - 55.0;
        $pdf->setFill(...$navy);
        $pdf->text($rx, $ry, 'INVOICE', 18, 'F2');
        $ry -= 22;
        $meta = [
            'Invoice no.' => $b['invoice_no'],
            'Booking no.' => $b['booking_no'],
            'Booking date' => date('d M Y', strtotime($b['created_at'])),
            'Service date' => date('d M Y (D)', strtotime($b['service_date'])),
        ];
        foreach ($meta as $k => $v) {
            $pdf->setFill(...$grey);
            $pdf->text($rx, $ry, $k, 9);
            $pdf->setFill(...$dark);
            $pdf->text($rx + 70, $ry, (string) $v, 9, 'F2');
            $ry -= 16;
        }

        // ---- divider + stamp ----
        $y = min($y, $ry) - 6;
        $pdf->setStroke(...$navy);
        $pdf->setLineWidth(0.8);
        $pdf->line($left, $y, $right, $y);
        $y -= 26;
        $pdf->setFill(...$navy);
        $pdf->text($left, $y, $this->stamp($b), 11, 'F2');
        $y -= 28;

        // ---- billed to + status ----
        $pdf->setFill(...$dark);
        $pdf->text($left, $y, 'Billed to', 8, 'F2');
        $y -= 14;
        $pdf->text($left, $y, $b['devotee_name'], 11, 'F2');
        $y -= 15;
        $pdf->setFill(...$grey);
        $pdf->text($left, $y, 'Mobile: ' . fmt_mobile($b['mobile']), 9);
        if ($b['email']) {
            $y -= 13;
            $pdf->text($left, $y, 'Email: ' . $b['email'], 9);
        }
        $y -= 16;
        $pdf->setFill(...$dark);
        $pdf->text($left, $y, 'Booking: ' . status_label('booking', $b['booking_status']), 9);
        $y -= 13;
        $pdf->text($left, $y, 'Payment: ' . status_label('payment', $b['payment_status']), 9);
        if ($b['approval_status'] !== 'not_required') {
            $y -= 13;
            $pdf->text($left, $y, 'Approval: ' . status_label('approval', $b['approval_status']), 9);
        }
        if ($b['payment_ref']) {
            $y -= 13;
            $pdf->text($left, $y, 'Payment ref: ' . $b['payment_ref'], 9);
        }
        $y -= 20;

        // ---- items table ----
        $colDesc = $left + 20;
        $colUnit = $right - 150;
        $colQty = $right - 100;
        $colAmt = $right;

        $pdf->setStroke(...$navy);
        $pdf->setLineWidth(0.6);
        $pdf->line($left, $y, $right, $y);
        $y -= 16;
        $pdf->setFill(...$dark);
        $pdf->text($left, $y, '#', 8, 'F2');
        $pdf->text($colDesc, $y, 'Description', 8, 'F2');
        $pdf->text($colUnit, $y, 'Unit price', 8, 'F2');
        $pdf->text($colQty, $y, 'Qty', 8, 'F2');
        $pdf->text($colAmt - $pdf->textWidth('Amount', 8, true), $y, 'Amount', 8, 'F2');
        $y -= 13;
        $pdf->line($left, $y, $right, $y);
        $y -= 12;

        $n = 0;
        foreach ($items as $it) {
            $n++;
            $desc = $it['name'] . ($it['item_type'] === 'food' ? ' (per person)' : '');
            if ($it['slot_label']) {
                $desc .= ' — Time: ' . $it['slot_label'];
            }
            if ($it['participants']) {
                $desc .= ' — Participants: ' . (int) $it['participants'];
            }
            foreach ($it['details'] as $dd) {
                $desc .= ' — ' . $dd['label'] . ': ' . $dd['value'];
            }

            $pdf->setFill(...$dark);
            $pdf->text($left, $y, (string) $n, 9);
            $lines = $pdf->wrap($desc, 9, $colUnit - $colDesc - 8) ?: [''];
            $pdf->text($colDesc, $y, $lines[0], 9);
            $pdf->setFill(...$grey);
            $pdf->text($colUnit, $y, money((int) $it['unit_price_paise']), 9);
            $pdf->text($colQty, $y, (string) (int) $it['quantity'], 9);
            $pdf->setFill(...$dark);
            $amt = money((int) $it['amount_paise']);
            $pdf->text($colAmt - $pdf->textWidth($amt, 9, true), $y, $amt, 9, 'F2');
            $y -= 13;
            foreach (array_slice($lines, 1) as $ln) {
                $pdf->text($colDesc, $y, $ln, 9);
                $y -= 13;
            }
        }

        // ---- totals ----
        $y -= 6;
        $pdf->line($left, $y, $right, $y);
        $y -= 14;
        $totRows = [
            ['Subtotal', money((int) $b['subtotal_paise']), false],
            [$b['additional_label'] ?: 'Additional charges', money((int) $b['additional_paise']), false],
            ['Total payable', money((int) $b['total_paise']), true],
        ];
        if ($b['payment_status'] === 'paid') {
            $totRows[] = ['Amount paid', money((int) ($payment['amount_paise'] ?? $b['total_paise'])), false];
        }
        foreach ($totRows as [$k, $v, $bold]) {
            $size = $bold ? 11 : 9;
            $pdf->setFill(...($bold ? $navy : $dark));
            $pdf->text($left, $y, $k, $size, $bold ? 'F2' : 'F1');
            $pdf->text($colAmt - $pdf->textWidth($v, $size, $bold), $y, $v, $size, $bold ? 'F2' : 'F1');
            $y -= 16;
        }

        // ---- QR (dynamic mode, payment still pending) ----
        $uri = $this->upi->uri($b);
        $canPay = in_array($b['payment_status'], ['pending', 'failed'], true) && in_array($b['booking_status'], ['pending', 'confirmed'], true);
        if ($uri && $canPay) {
            $matrix = qr_matrix($uri);
            if ($matrix !== []) {
                $qrSize = 100.0;
                $pdf->matrix($left, $y - $qrSize, $qrSize, $matrix);
                $lx = $left + $qrSize + 12;
                $pdf->setFill(...$grey);
                $pdf->text($lx, $y - 30, 'Scan with any UPI app to pay ' . money((int) $b['total_paise']) . '.', 8);
                $pdf->text($lx, $y - 42, 'Note: ' . $b['booking_no'], 8);
                $y -= $qrSize + 18;
            }
        }

        // ---- instructions, terms, footer ----
        if ($b['instructions']) {
            $y -= 8;
            $pdf->setFill(...$dark);
            foreach ($pdf->wrap('Instructions: ' . $b['instructions'], 8, $width) as $ln) {
                $pdf->text($left, $y, $ln, 8);
                $y -= 11;
            }
        }
        if ($terms !== '') {
            $y -= 8;
            $pdf->setFill(...$grey);
            foreach ($pdf->wrap($terms, 8, $width) as $ln) {
                $pdf->text($left, $y, $ln, 8);
                $y -= 11;
            }
        }
        $pdf->setFill(...$grey);
        $pdf->text($left, 45, 'This is a computer-generated invoice. Please quote booking number ' . $b['booking_no'] . ' in all communication. Jai Jagannath!', 8);

        return $pdf->render();
    }

    private function stamp(array $b): string
    {
        return match (true) {
            $b['booking_status'] === 'cancelled' => 'CANCELLED',
            $b['booking_status'] === 'rejected' => 'NOT APPROVED',
            $b['payment_status'] === 'refunded' => 'REFUNDED',
            $b['payment_status'] === 'paid' => 'PAID',
            $b['payment_status'] === 'not_required' => 'NO PAYMENT DUE',
            $b['payment_status'] === 'awaiting_verification' => 'PAYMENT UNDER VERIFICATION',
            default => 'PAYMENT PENDING',
        };
    }
}
