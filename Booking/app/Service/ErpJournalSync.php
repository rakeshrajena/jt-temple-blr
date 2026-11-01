<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\Logger;
use SJT\Repo\Bookings;

/**
 * Posts verified online booking payments to ERP donations (cash book / ledger).
 * Paid booking cancellations go through ERP receipt-cancellation approval.
 */
final class ErpJournalSync
{
    public function __construct(
        private Database $db,
        private Bookings $bookings,
    ) {
    }

    public function tablesReady(): bool
    {
        try {
            return (bool) $this->db->value(
                $this->db->driver === 'mysql'
                    ? "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'donations' LIMIT 1"
                    : "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'donations' LIMIT 1"
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Create ERP donation when payment is verified (manual admin confirm, Razorpay, etc.).
     * Posts to cash book / ledger; idempotent per booking number.
     *
     * @return int|null ERP donation id when linked
     */
    public function syncIfPaidAndConfirmed(array $b, ?array $actor): ?int
    {
        if (!$this->tablesReady() || (int) ($b['total_paise'] ?? 0) <= 0) {
            return null;
        }
        if (($b['payment_status'] ?? '') !== 'paid') {
            return null;
        }
        if (in_array($b['booking_status'] ?? '', ['cancelled', 'rejected'], true)) {
            return null;
        }
        $this->ensureLinkSchema();
        if (!empty($b['erp_donation_id'])) {
            $id = (int) $b['erp_donation_id'];
            $this->finalizeDonationReceipt($id, $actor);
            return $id;
        }
        try {
            $donationId = $this->createDonation($b, $actor);
            if ($donationId > 0) {
                $this->db->update('bookings', [
                    'erp_donation_id' => $donationId,
                    'updated_at' => now(),
                ], 'id = :id', ['id' => (int) $b['id']]);
                return $donationId;
            }
        } catch (\Throwable $e) {
            Logger::error('ERP journal sync failed', ['booking' => $b['booking_no'] ?? '', 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Submit ERP receipt cancellation (Waiting). Booking stays confirmed until Treasurer/Admin approves.
     *
     * @return array{awaiting_approval: bool, error: ?string}
     */
    public function requestPaidCancellation(array $b, array $actor, string $reason): array
    {
        if (!$this->tablesReady() || ($b['payment_status'] ?? '') !== 'paid') {
            return ['awaiting_approval' => false, 'error' => null];
        }
        if (($b['cancellation_status'] ?? '') === 'awaiting_approval') {
            return ['awaiting_approval' => true, 'error' => 'A cancellation is already waiting for ERP approval.'];
        }
        $donationId = (int) ($b['erp_donation_id'] ?? 0);
        if ($donationId <= 0) {
            $this->syncIfPaidAndConfirmed($b, $actor);
            $fresh = $this->bookings->find((int) $b['id']);
            $donationId = (int) ($fresh['erp_donation_id'] ?? 0);
        }
        if ($donationId <= 0) {
            return ['awaiting_approval' => false, 'error' => 'Could not link this booking to the temple ledger. Sync the payment first or contact an administrator.'];
        }
        $donation = $this->db->one('SELECT id, amount, receipt_generated, receipt_cancelled FROM donations WHERE id = ?', [$donationId]);
        if (!$donation) {
            return ['awaiting_approval' => false, 'error' => 'The linked ledger entry was not found.'];
        }
        if ((int) ($donation['receipt_cancelled'] ?? 0) === 1) {
            return ['awaiting_approval' => false, 'error' => null];
        }
        if ($this->pendingReceiptCancel($donationId)) {
            return ['awaiting_approval' => true, 'error' => 'A cancellation is already waiting for ERP approval.'];
        }
        if ((int) ($donation['receipt_generated'] ?? 0) !== 1) {
            $this->assignReceiptNumber($donationId);
            $donation['receipt_generated'] = 1;
        }
        $userId = $this->erpUserId($actor);
        if ($userId <= 0) {
            return ['awaiting_approval' => false, 'error' => 'Could not resolve your ERP user for the cancellation request.'];
        }
        $cancelId = (int) $this->db->insert('receipt_cancellations', [
            'donation_id' => $donationId,
            'reason' => mb_substr(trim($reason), 0, 500),
            'prepared_by' => $userId,
        ]);
        $amount = round((int) $b['total_paise'] / 100, 2);
        $this->recordApproval('receipt', $cancelId, 'Waiting', $amount, $userId);
        $this->db->update('bookings', [
            'erp_receipt_cancel_id' => $cancelId,
            'cancellation_status' => 'awaiting_approval',
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $b['id']]);
        return ['awaiting_approval' => true, 'error' => null];
    }

    private function ensureLinkSchema(): void
    {
        if ($this->db->driver !== 'mysql') {
            return;
        }
        $add = function (string $table, string $col, string $def): void {
            $exists = $this->db->value(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $col]
            );
            if (!$exists) {
                $this->db->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        };
        $add('bookings', 'erp_donation_id', 'INT NULL');
        $add('bookings', 'erp_receipt_cancel_id', 'INT NULL');
        $add('bookings', 'cancellation_status', 'VARCHAR(30) NULL');
        if (!$this->tablesReady()) {
            return;
        }
        $add('donations', 'online_booking_id', 'INT NULL');
        $add('donations', 'online_booking_no', 'VARCHAR(30) NULL');
    }

    private function createDonation(array $b, ?array $actor): int
    {
        $existing = $this->findExistingDonation((string) $b['booking_no']);
        if ($existing !== null) {
            return $existing;
        }
        $donorId = $this->ensureDonor($b);
        $purpose = $this->resolvePurpose((int) $b['id']);
        $amount = round((int) $b['total_paise'] / 100, 2);
        $donationDate = substr((string) ($b['paid_at'] ?? $b['created_at'] ?? date('Y-m-d H:i:s')), 0, 10);
        $pay = $this->db->one(
            "SELECT method, reference FROM payments WHERE booking_id = ? AND status = 'verified' ORDER BY id DESC LIMIT 1",
            [(int) $b['id']]
        );
        $mode = $this->paymentMode($pay['method'] ?? '');
        $upiRef = $mode === 'UPI' ? mb_substr(trim((string) ($pay['reference'] ?? $b['payment_ref'] ?? '')), 0, 64) : null;
        $notes = 'Online booking ' . $b['booking_no'] . ' · Invoice ' . ($b['invoice_no'] ?? '');
        $createdBy = $this->erpUserId($actor);
        $row = [
            'donor_id' => $donorId,
            'donation_type' => 'Cash',
            'amount' => $amount,
            'purpose' => mb_substr($purpose, 0, 200),
            'donation_date' => $donationDate,
            'payment_mode' => $mode,
            'upi_reference' => $upiRef !== '' ? $upiRef : null,
            'notes' => $notes,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'receipt_generated' => 0,
            'receipt_cancelled' => 0,
            'cheque_cleared' => 0,
        ];
        if ($this->columnExists('donations', 'online_booking_id')) {
            $row['online_booking_id'] = (int) $b['id'];
            $row['online_booking_no'] = $b['booking_no'];
        }
        $id = (int) $this->db->insert('donations', $row);
        $this->finalizeDonationReceipt($id, $actor);
        return $id;
    }

    /**
     * Backfill ERP donations + receipts for all paid bookings (skips cancelled/rejected).
     *
     * @return array{processed: int, linked: int, receipts: int, skipped: int, errors: list<string>}
     */
    public function backfillAllPaid(?array $actor = null): array
    {
        $stats = ['processed' => 0, 'linked' => 0, 'receipts' => 0, 'skipped' => 0, 'errors' => []];
        if (!$this->tablesReady()) {
            $stats['errors'][] = 'ERP donations table is not available.';
            return $stats;
        }
        $this->ensureLinkSchema();
        $rows = $this->db->all(
            "SELECT * FROM bookings
             WHERE payment_status = 'paid' AND total_paise > 0
             AND booking_status NOT IN ('cancelled', 'rejected')
             ORDER BY id"
        );
        foreach ($rows as $b) {
            ++$stats['processed'];
            $before = (int) ($b['erp_donation_id'] ?? 0);
            try {
                $donationId = $this->syncIfPaidAndConfirmed($b, $actor);
                if ($donationId === null) {
                    ++$stats['skipped'];
                    continue;
                }
                if ($before <= 0) {
                    ++$stats['linked'];
                }
                if ($this->donationHasReceipt($donationId)) {
                    ++$stats['receipts'];
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = ($b['booking_no'] ?? '?') . ': ' . $e->getMessage();
            }
        }
        return $stats;
    }

    private function donationHasReceipt(int $donationId): bool
    {
        $row = $this->db->one('SELECT receipt_number, receipt_generated FROM donations WHERE id = ?', [$donationId]);
        return $row && trim((string) ($row['receipt_number'] ?? '')) !== '' && (int) ($row['receipt_generated'] ?? 0) === 1;
    }

    private function finalizeDonationReceipt(int $donationId, ?array $actor): void
    {
        if ($this->issueDonationReceiptCli($donationId, $this->erpUserId($actor) ?: null)) {
            return;
        }
        $this->assignReceiptNumber($donationId);
    }

    private function issueDonationReceiptCli(int $donationId, ?int $userId): bool
    {
        $script = TempleLogo::erpRoot() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'issue_donation_receipt.php';
        if (!is_file($script)) {
            return false;
        }
        $php = PHP_BINARY ?: 'php';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $donationId;
        if ($userId !== null && $userId > 0) {
            $cmd .= ' ' . $userId;
        }
        $lines = [];
        $code = 0;
        exec($cmd . ' 2>&1', $lines, $code);
        if ($code !== 0) {
            Logger::warning('ERP receipt CLI failed', ['donation' => $donationId, 'output' => implode("\n", $lines)]);
            return false;
        }
        return true;
    }

    private function findExistingDonation(string $bookingNo): ?int
    {
        if ($this->columnExists('donations', 'online_booking_no')) {
            $row = $this->db->one('SELECT id FROM donations WHERE online_booking_no = ? LIMIT 1', [$bookingNo]);
            if ($row) {
                return (int) $row['id'];
            }
        }
        $like = 'Online booking ' . $bookingNo . '%';
        $row = $this->db->one('SELECT id FROM donations WHERE notes LIKE ? ORDER BY id DESC LIMIT 1', [$like]);
        return $row ? (int) $row['id'] : null;
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->db->driver === 'mysql') {
            return (bool) $this->db->value(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
        }
        $cols = array_column($this->db->pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
        return in_array($column, $cols, true);
    }

    private function ensureDonor(array $b): int
    {
        $phone = $this->normaliseMobile((string) $b['mobile']);
        if ($phone !== '') {
            $row = $this->db->one('SELECT id FROM donors WHERE phone = ? LIMIT 1', [$phone]);
            if ($row) {
                return (int) $row['id'];
            }
        }
        return (int) $this->db->insert('donors', [
            'name' => mb_substr(trim((string) $b['devotee_name']), 0, 150),
            'phone' => $phone !== '' ? $phone : null,
            'email' => trim((string) ($b['email'] ?? '')) ?: null,
        ]);
    }

    private function resolvePurpose(int $bookingId): string
    {
        $name = $this->db->value(
            "SELECT name FROM booking_items WHERE booking_id = ? AND item_type = 'service' ORDER BY sort_order, id LIMIT 1",
            [$bookingId]
        );
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }
        $food = $this->db->value(
            "SELECT name FROM booking_items WHERE booking_id = ? AND item_type = 'food' ORDER BY sort_order, id LIMIT 1",
            [$bookingId]
        );
        return is_string($food) && trim($food) !== '' ? trim($food) : 'Online seva booking';
    }

    private function paymentMode(string $method): string
    {
        return match ($method) {
            'recorded_by_admin' => 'Cash',
            default => 'UPI',
        };
    }

    private function normaliseMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }
        return $digits;
    }

    private function erpUserId(?array $actor): int
    {
        if (!$actor || empty($actor['id'])) {
            return 0;
        }
        $id = (int) $actor['id'];
        if ($this->db->one('SELECT id FROM users WHERE id = ?', [$id])) {
            return $id;
        }
        $username = str_starts_with((string) ($actor['name'] ?? ''), 'admin:')
            ? substr((string) $actor['name'], 6) : null;
        if ($username) {
            $row = $this->db->one('SELECT id FROM users WHERE username = ?', [$username]);
            if ($row) {
                return (int) $row['id'];
            }
        }
        return 0;
    }

    private function assignReceiptNumber(int $donationId): void
    {
        $row = $this->db->one('SELECT receipt_number, receipt_generated FROM donations WHERE id = ?', [$donationId]);
        if (!$row || trim((string) ($row['receipt_number'] ?? '')) !== '') {
            return;
        }
        $number = $this->nextReceiptNumber();
        $this->db->update('donations', [
            'receipt_number' => $number,
            'receipt_generated' => 1,
        ], 'id = :id', ['id' => $donationId]);
        try {
            $this->db->insert('receipts', [
                'donation_id' => $donationId,
                'receipt_number' => $number,
                'generated_by' => null,
            ]);
        } catch (\Throwable) {
            // receipts row optional if table missing in tests
        }
    }

    private function nextReceiptNumber(): string
    {
        $rows = $this->db->all("SELECT receipt_number FROM donations WHERE receipt_number LIKE 'RCPT-%'");
        $highest = 0;
        foreach ($rows as $row) {
            $n = (string) ($row['receipt_number'] ?? '');
            if (preg_match('/^RCPT-\d{10}-(\d{4,6})$/', $n, $m) === 1) {
                $highest = max($highest, (int) $m[1]);
            } elseif (preg_match('/^RCPT-\d{4}-(\d{4})$/', $n, $m) === 1) {
                $highest = max($highest, (int) $m[1]);
            }
        }
        return sprintf('RCPT-%d-%04d', time(), $highest + 1);
    }

    private function pendingReceiptCancel(int $donationId): bool
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM receipt_cancellations rc
             JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id
             WHERE rc.donation_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')",
            [$donationId]
        ) > 0;
    }

    private function recordApproval(string $type, int $subjectId, string $status, float $amount, int $userId): void
    {
        $this->db->insert('approvals', [
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'status' => $status,
            'amount' => round($amount, 2),
            'prepared_by' => $userId,
        ]);
    }
}
