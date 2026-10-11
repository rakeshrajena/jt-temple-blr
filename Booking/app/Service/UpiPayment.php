<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Repo\Settings;

/**
 * UPI QR payments.
 *  - dynamic mode: a QR is generated per booking with the temple's UPI ID, the
 *    exact amount and the booking number as the payment note.
 *  - static mode : the temple's own authorised QR image is shown, with the
 *    amount and booking number printed next to it.
 * Neither mode is shown to devotees until an administrator has confirmed the
 * UPI ID / QR belongs to the temple ("upi_verified" setting).
 */
final class UpiPayment
{
    public const STATIC_QR_FILE = APP_ROOT . '/storage/uploads/upi-qr.png';

    public function __construct(private Settings $settings)
    {
    }

    public function mode(): string
    {
        return $this->settings->get('upi_qr_mode', 'dynamic') === 'static' ? 'static' : 'dynamic';
    }

    /** Ready for devotees: verified by an admin and the needed details are present. */
    public function available(): bool
    {
        if (!$this->settings->bool('upi_verified')) {
            return false;
        }
        return $this->mode() === 'static' ? is_file(self::STATIC_QR_FILE) : $this->validVpa((string) $this->settings->get('upi_vpa'));
    }

    public function validVpa(string $vpa): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z][a-zA-Z0-9.\-]{1,64}$/', $vpa);
    }

    /** upi://pay deep link with amount and booking reference (dynamic mode only). */
    public function uri(array $b): ?string
    {
        $vpa = (string) $this->settings->get('upi_vpa');
        if (!$this->validVpa($vpa)) {
            return null;
        }
        $params = [
            'pa' => $vpa,
            'pn' => mb_substr((string) $this->settings->get('upi_payee_name', 'Temple'), 0, 50),
            'am' => number_format(((int) $b['total_paise']) / 100, 2, '.', ''),
            'cu' => 'INR',
            'tn' => $b['booking_no'],
        ];
        if ($this->settings->bool('upi_include_tr')) {
            // Merchant transaction reference — only for merchant/business VPAs.
            $params['tr'] = preg_replace('/[^A-Za-z0-9]/', '', $b['booking_no']);
        }
        // Most UPI apps expect a literal "@" in the payee address.
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k . '=' . str_replace('%40', '@', rawurlencode((string) $v));
        }
        return 'upi://pay?' . implode('&', $pairs);
    }

    public function qrPng(string $data, int $scale = 8): string
    {
        return qr_png($data, $scale);
    }

    public function qrSvg(string $data): string
    {
        return qr_svg($data);
    }

    /** PNG bytes of the QR for this booking (dynamic) or the uploaded static QR. */
    public function bookingQrPng(array $b): ?string
    {
        if ($this->mode() === 'static') {
            return is_file(self::STATIC_QR_FILE) ? (string) file_get_contents(self::STATIC_QR_FILE) : null;
        }
        $uri = $this->uri($b);
        return $uri ? $this->qrPng($uri) : null;
    }

    /**
     * Store an uploaded QR image. The image is decoded and re-encoded as PNG,
     * which strips any non-image content. Returns an error message or null.
     */
    public function storeStaticQr(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
            return 'Please choose an image file to upload.';
        }
        if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
            return 'The image must be smaller than 3 MB.';
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return 'Upload a PNG, JPG or WebP image of the QR code.';
        }
        $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$img) {
            return 'The image could not be read.';
        }
        @mkdir(dirname(self::STATIC_QR_FILE), 0750, true);
        imagepng($img, self::STATIC_QR_FILE, 6);
        imagedestroy($img);
        return null;
    }
}
