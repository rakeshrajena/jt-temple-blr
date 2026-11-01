<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\Logger;
use SJT\Lib\SmtpMail;
use SJT\Repo\Settings;

/** Sends booking receipt emails using ERP SMTP settings (app_settings) and invoice PDF. */
final class BookingMailer
{
    /** @var array<string, string>|null */
    private ?array $smtpCache = null;

    public function __construct(
        private Database $db,
        private Settings $settings,
        private InvoiceRenderer $invoices,
    ) {
    }

    public function devoteeAddress(array $b): ?string
    {
        $email = trim((string) ($b['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return $email;
    }

    /**
     * @param array{subject: string, body: string, attach_invoice?: bool} $payload
     */
    public function sendPayload(array $b, string $to, array $payload): ?string
    {
        $subject = trim($payload['subject'] ?? '');
        $body = trim($payload['body'] ?? '');
        if ($subject === '' || $body === '') {
            return 'The email content is incomplete.';
        }
        $smtp = $this->smtpSettings();
        if (!SmtpMail::isReady($smtp)) {
            return 'Outgoing mail is not configured (ERP Settings › Messages).';
        }
        $attachment = null;
        if (($payload['attach_invoice'] ?? true) === true) {
            try {
                $pdf = $this->invoices->pdf($b);
                $attachment = [
                    'filename' => 'Invoice-' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) $b['booking_no']) . '.pdf',
                    'content' => $pdf,
                    'mime' => 'application/pdf',
                ];
            } catch (\Throwable $e) {
                Logger::warning('Invoice PDF for email failed', ['booking' => $b['booking_no'], 'error' => $e->getMessage()]);
            }
        }
        $temple = trim($this->settings->get('temple_name', 'Shree Jagannatha Temple Bengaluru'));
        // Booking-local SMTP (plain + optional PDF). ERP send_smtp_message() needs ERP db helpers and nested MIME some hosts reject.
        $err = SmtpMail::send($smtp, $to, $subject, $body, $temple, $attachment, null);
        if ($err === null || $attachment === null) {
            return $err;
        }
        Logger::warning('Booking email with PDF refused; retrying without attachment', ['booking' => $b['booking_no'], 'error' => $err]);
        return SmtpMail::send(
            $smtp,
            $to,
            $subject,
            $body . "\r\n\r\n(Invoice PDF could not be attached; use the booking link above to download it.)",
            $temple,
            null,
            null,
        );
    }

    public function bookingReceivedBody(array $b, array $ctx): string
    {
        return $this->compose(
            $b,
            BookingDevoteeCopy::emailLeadCreated($b),
            $ctx,
            BookingDevoteeCopy::emailFooterCreated($b),
        );
    }

    public function statusBody(array $b, string $message, array $ctx): string
    {
        return $this->compose($b, $message, $ctx, BookingDevoteeCopy::emailFooterUpdate($b));
    }

    /** @param array{link: string, status: string} $ctx */
    private function compose(array $b, string $lead, array $ctx, string $footer): string
    {
        $lines = [
            'Namaskar ' . $b['devotee_name'] . ',',
            '',
            $lead,
            '',
            'Booking number: ' . $b['booking_no'],
            'Invoice number: ' . ($b['invoice_no'] ?? '-'),
            'Services: ' . ($ctx['services'] ?? '-'),
            'Date and time: ' . ($ctx['when'] ?? '-'),
            'Food coupons: ' . ($ctx['food'] ?? '-'),
            'Total amount: ' . money((int) $b['total_paise']),
            'Overall status: ' . ($ctx['status'] ?? Status::headline($b)[1]),
            'Booking status: ' . status_label('booking', $b['booking_status']),
            'Payment status: ' . status_label('payment', $b['payment_status']),
            '',
            'View your booking, pay, or download your invoice:',
            $ctx['link'] ?? absolute_url('/b/' . $b['booking_no'], ['t' => $b['access_token']]),
            '',
            $footer,
        ];
        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    private function smtpSettings(): array
    {
        if ($this->smtpCache !== null) {
            return $this->smtpCache;
        }
        $defaults = [
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => '',
        ];
        try {
            if (!$this->db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'app_settings' LIMIT 1")) {
                return $this->smtpCache = $defaults;
            }
            $rows = $this->db->all(
                "SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'"
            );
            foreach ($rows as $row) {
                $key = (string) $row['setting_key'];
                if (array_key_exists($key, $defaults)) {
                    $defaults[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (\Throwable) {
            // SQLite tests: no information_schema — try direct read.
            try {
                $rows = $this->db->all("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'");
                foreach ($rows as $row) {
                    $key = (string) $row['setting_key'];
                    if (array_key_exists($key, $defaults)) {
                        $defaults[$key] = (string) ($row['setting_value'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // table missing
            }
        }
        return $this->smtpCache = $defaults;
    }
}
