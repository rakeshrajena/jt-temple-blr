<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\ValidationException;
use SJT\Repo\Bookings;
use SJT\Repo\Catalog;
use SJT\Repo\Settings;

/**
 * Creates bookings and applies every status change. All changes are written
 * in a transaction together with an audit-log entry; notifications are sent
 * only after the transaction has committed, so a WhatsApp failure can never
 * lose a booking.
 */
final class BookingService
{
    public function __construct(
        private Database $db,
        private Bookings $bookings,
        private Catalog $catalog,
        private Availability $availability,
        private Settings $settings,
        private Audit $audit,
        private Notifier $notifier,
    ) {
    }

    /**
     * Persist a validated draft. Returns [booking, created(bool)].
     * A repeated submission of the same form (same idempotency key) returns the
     * existing booking instead of creating a duplicate.
     */
    public function create(array $draft, string $idempotencyKey, string $ip): array
    {
        $result = $this->db->transaction(function (Database $db) use ($draft, $idempotencyKey, $ip) {
            if ($existing = $this->bookings->findByIdempotencyKey($idempotencyKey)) {
                return [$existing, false];
            }
            // Re-check capacity while holding locks so two devotees cannot take the last place.
            $errors = [];
            foreach ($draft['quote']['items'] as $i => $item) {
                if ($item['item_type'] !== 'service') {
                    continue;
                }
                $svc = $db->one('SELECT id FROM services WHERE id = ?' . $db->forUpdate(), [$item['service_id']]);
                $service = $this->catalog->service((int) $item['service_id']);
                if (!$svc || !$service) {
                    $errors['services'] = 'A selected service is no longer available.';
                    continue;
                }
                if ($p = $this->availability->serviceProblem($service, $draft['service_date'])) {
                    $errors['service_date'] = $p;
                }
                if ($item['slot_id']) {
                    $db->one('SELECT id FROM time_slots WHERE id = ?' . $db->forUpdate(), [$item['slot_id']]);
                    $slot = $this->catalog->slot((int) $item['slot_id']);
                    if (!$slot || ($p = $this->availability->slotProblem($service, $slot, $draft['service_date']))) {
                        $errors['slot.' . $item['service_id']] = $p ?? 'The chosen time slot is no longer available.';
                    }
                }
            }
            if ($errors) {
                throw new ValidationException($errors);
            }

            $created = now();
            $year = date('Y');
            $seq = new Sequence($db);
            $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($this->settings->get('booking_prefix', 'SJT'))) ?: 'SJT';
            $invPrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($this->settings->get('invoice_prefix', 'SJTB'))) ?: 'SJTB';
            $bookingNo = sprintf('%s-%s-%06d', $prefix, $year, $seq->next('booking', $year));
            $fy = Sequence::financialYear(today());
            $invoiceNo = sprintf('%s/%s/%06d', $invPrefix, $fy, $seq->next('invoice', $fy));

            $q = $draft['quote'];
            $needsApproval = (bool) array_filter($draft['services'], fn ($l) => $l['service']['requires_approval']);
            $payment = $q['total_paise'] > 0 ? 'pending' : 'not_required';
            $approval = $needsApproval ? 'pending' : 'not_required';

            $id = $db->insert('bookings', [
                'booking_no' => $bookingNo,
                'invoice_no' => $invoiceNo,
                'access_token' => bin2hex(random_bytes(16)),
                'idempotency_key' => $idempotencyKey,
                'booking_type' => $draft['booking_type'],
                'devotee_name' => $draft['name'],
                'mobile' => $draft['mobile'],
                'email' => $draft['email'],
                'service_date' => $draft['service_date'],
                'instructions' => $draft['instructions'] !== '' ? $draft['instructions'] : null,
                'subtotal_paise' => $q['subtotal_paise'],
                'additional_label' => $q['additional_label'],
                'additional_paise' => $q['additional_paise'],
                'total_paise' => $q['total_paise'],
                'payment_status' => $payment,
                'approval_status' => $approval,
                'booking_status' => Status::derive($payment, $approval, 'pending'),
                'created_ip' => $ip,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
            foreach ($q['items'] as $n => $it) {
                $db->insert('booking_items', [
                    'booking_id' => $id,
                    'item_type' => $it['item_type'],
                    'service_id' => $it['service_id'],
                    'coupon_id' => $it['coupon_id'],
                    'slot_id' => $it['slot_id'],
                    'slot_label' => $it['slot_label'],
                    'name' => $it['name'],
                    'unit_price_paise' => $it['unit_price_paise'],
                    'quantity' => $it['quantity'],
                    'amount_paise' => $it['amount_paise'],
                    'participants' => $it['participants'],
                    'details_json' => $it['details'] ? json_encode($it['details'], JSON_UNESCAPED_UNICODE) : null,
                    'sort_order' => $n,
                ]);
            }
            $this->audit->log(Audit::devotee(), 'booking', $bookingNo, $id, 'booking.created', [
                'total' => $q['total_paise'], 'payment_status' => $payment, 'approval_status' => $approval,
            ]);
            return [$this->bookings->find($id), true];
        });

        if ($result[1]) {
            $this->notifier->bookingCreated($result[0]);
        }
        return $result;
    }

    // ------------------------------------------------------------------ devotee actions

    /** Devotee submits a UPI reference. This never marks the booking as paid. */
    public function submitPayment(array $b, string $referenceInput, string $note = ''): array
    {
        $ref = Validator::paymentReference($referenceInput);
        if (!$ref) {
            throw new ValidationException(['reference' => 'Please enter the UPI reference / UTR number exactly as shown in your UPI app (usually 12 digits).']);
        }
        if (!in_array($b['payment_status'], ['pending', 'failed'], true) || !in_array($b['booking_status'], ['pending', 'confirmed'], true)) {
            throw new ValidationException(['reference' => 'A payment reference cannot be submitted for this booking in its current state.']);
        }
        $other = $this->db->one(
            "SELECT b.booking_no FROM payments p JOIN bookings b ON b.id = p.booking_id
             WHERE p.reference = ? AND p.status IN ('submitted','verified') AND p.booking_id <> ?",
            [$ref, $b['id']]
        );
        if ($other) {
            throw new ValidationException(['reference' => 'This reference number is already recorded against another booking. If you made a separate payment, please enter that reference, or contact the temple office.']);
        }
        $this->db->transaction(function (Database $db) use ($b, $ref, $note) {
            $cur = $this->lock($b['id']);
            if (!in_array($cur['payment_status'], ['pending', 'failed'], true)) {
                throw new ValidationException(['reference' => 'A payment reference has already been submitted for this booking.']);
            }
            $db->insert('payments', [
                'booking_id' => $b['id'], 'method' => 'upi_manual', 'reference' => $ref,
                'amount_paise' => (int) $cur['total_paise'], 'status' => 'submitted',
                'payer_note' => Validator::text($note, 255) ?: null, 'submitted_at' => now(),
            ]);
            $this->setStatus($cur, ['payment_status' => 'awaiting_verification', 'payment_ref' => $ref]);
            $this->audit->log(Audit::devotee(), 'payment', $ref, (int) $b['id'], 'payment.submitted', ['reference' => $ref, 'amount' => (int) $cur['total_paise']]);
        });
        $fresh = $this->bookings->find((int) $b['id']);
        $this->notifier->paymentSubmitted($fresh, $ref);
        return $fresh;
    }

    // ------------------------------------------------------------------ admin actions

    public function approve(array $b, array $actor, string $note = ''): array
    {
        return $this->change($b, $actor, 'booking.approved', function (array $cur) {
            if ($cur['approval_status'] !== 'pending' || !in_array($cur['booking_status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['action' => 'This booking is not awaiting approval.']);
            }
            return ['approval_status' => 'approved'];
        }, ['note' => $note], fn ($n) => 'Your booking has been approved by the temple office.' . ($n['booking_status'] === 'confirmed' ? ' It is now confirmed.' : ' It will be confirmed once payment is verified.') . ($note ? ' Note: ' . $note : ''));
    }

    public function reject(array $b, array $actor, string $reason): array
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'Please enter a reason. It is shared with the devotee.']);
        }
        return $this->change($b, $actor, 'booking.rejected', function (array $cur) {
            if (!in_array($cur['booking_status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['action' => 'Only open bookings can be rejected.']);
            }
            return ['approval_status' => 'rejected', 'booking_status' => 'rejected'];
        }, ['reason' => $reason], fn ($n) => 'The temple office could not approve your booking. Reason: ' . $reason . ($n['payment_status'] === 'paid' ? ' Your payment will be refunded; the temple office will contact you.' : ''));
    }

    public function cancel(array $b, array $actor, string $reason): array
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'Please enter a reason for cancelling.']);
        }
        return $this->change($b, $actor, 'booking.cancelled', function (array $cur) {
            if (!in_array($cur['booking_status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['action' => 'This booking is already closed.']);
            }
            return ['booking_status' => 'cancelled'];
        }, ['reason' => $reason], fn ($n) => 'Your booking has been cancelled. Reason: ' . $reason . ($n['payment_status'] === 'paid' ? ' The temple office will contact you about the refund.' : ''));
    }

    public function complete(array $b, array $actor): array
    {
        return $this->change($b, $actor, 'booking.completed', function (array $cur) {
            if ($cur['booking_status'] !== 'confirmed') {
                throw new ValidationException(['action' => 'Only confirmed bookings can be marked as completed.']);
            }
            return ['booking_status' => 'completed'];
        }, [], null);
    }

    /**
     * Move a booking to a new date and/or slots. $slots maps booking_item id => slot id.
     * Capacity is checked unless $override is true (recorded in the audit log).
     */
    public function reschedule(array $b, array $actor, string $newDate, array $slots, bool $override, string $note): array
    {
        $date = Validator::date($newDate);
        if (!$date) {
            throw new ValidationException(['date' => 'Please choose a valid new date.']);
        }
        $old = [];
        $this->db->transaction(function (Database $db) use ($b, $actor, $date, $slots, $override, $note, &$old) {
            $cur = $this->lock($b['id']);
            if (!in_array($cur['booking_status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['action' => 'Only open bookings can be rescheduled.']);
            }
            $items = $this->bookings->items((int) $b['id']);
            $errors = [];
            $changes = [];
            foreach ($items as $it) {
                if ($it['item_type'] !== 'service') {
                    continue;
                }
                $svc = $this->catalog->service((int) $it['service_id']);
                if (!$svc) {
                    continue;
                }
                if (!$override && ($p = $this->availability->serviceProblem($svc, $date, (int) $b['id'], false))) {
                    $errors['date'] = $p;
                }
                $newSlotId = isset($slots[$it['id']]) ? (int) $slots[$it['id']] : (int) $it['slot_id'];
                if ($svc['requires_slot']) {
                    $db->one('SELECT id FROM time_slots WHERE id = ?' . $db->forUpdate(), [$newSlotId]);
                    $slot = $newSlotId ? $this->catalog->slot($newSlotId) : null;
                    if (!$slot || $slot['service_id'] !== $svc['id']) {
                        $errors['slot'] = 'Please choose a valid time for ' . $svc['name'] . '.';
                        continue;
                    }
                    if (!$override && ($p = $this->availability->slotProblem($svc, $slot, $date, (int) $b['id']))) {
                        $errors['slot'] = $p;
                    }
                    $changes[$it['id']] = $slot;
                }
            }
            if ($errors) {
                throw new ValidationException($errors + ['override' => 'Tick "Override capacity" to reschedule anyway.']);
            }
            $old = ['date' => $cur['service_date'], 'slots' => array_column($items, 'slot_label', 'id')];
            foreach ($changes as $itemId => $slot) {
                $db->update('booking_items', ['slot_id' => $slot['id'], 'slot_label' => $slot['label']], 'id = :id', ['id' => $itemId]);
            }
            $this->setStatus($cur, ['service_date' => $date]);
            $this->audit->log($actor, 'booking', $cur['booking_no'], (int) $b['id'], 'booking.rescheduled', [
                'from' => $old, 'to' => ['date' => $date, 'slots' => array_map(fn ($s) => $s['label'], $changes)],
                'override' => $override, 'note' => $note,
            ]);
        });
        $fresh = $this->bookings->find((int) $b['id']);
        $when = fmt_date($date) . ($this->bookings->slotSummary((int) $b['id']) ? ', ' . $this->bookings->slotSummary((int) $b['id']) : '');
        $this->notifier->statusUpdate($fresh, 'Your booking has been rescheduled to ' . $when . '.' . ($note ? ' Note: ' . $note : ''));
        return $fresh;
    }

    /** Admin verifies a submitted payment, or records one received directly (counter/UPI) with a reference. */
    public function verifyPayment(array $b, array $actor, ?int $paymentId, string $referenceInput, string $amountInput, string $note): array
    {
        $ref = Validator::paymentReference($referenceInput);
        $amount = parse_rupees($amountInput);
        $errors = [];
        if (!$ref) {
            $errors['reference'] = 'Enter the UPI reference / UTR exactly as shown in the temple bank statement.';
        }
        if ($amount === null || $amount <= 0) {
            $errors['amount'] = 'Enter the amount received, as shown in the bank statement.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if ($amount !== (int) $b['total_paise'] && trim($note) === '') {
            throw new ValidationException(['note' => 'The amount received (' . money($amount) . ') differs from the amount due (' . money((int) $b['total_paise']) . '). Add a note explaining the difference to continue.']);
        }
        $this->db->transaction(function (Database $db) use ($b, $actor, $paymentId, $ref, $amount, $note) {
            $cur = $this->lock($b['id']);
            if (!in_array($cur['payment_status'], ['pending', 'awaiting_verification', 'failed'], true)) {
                throw new ValidationException(['action' => 'Payment for this booking is already ' . status_label('payment', $cur['payment_status']) . '.']);
            }
            $dupe = $db->value("SELECT b.booking_no FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.reference = ? AND p.status = 'verified' AND p.booking_id <> ?", [$ref, $b['id']]);
            if ($dupe) {
                throw new ValidationException(['reference' => "This reference is already verified on booking $dupe. Check the bank statement."]);
            }
            $row = ['reference' => $ref, 'amount_paise' => $amount, 'status' => 'verified', 'reviewed_at' => now(), 'reviewed_by' => $actor['name'], 'review_note' => $note ?: null];
            $pay = $paymentId ? $db->one("SELECT * FROM payments WHERE id = ? AND booking_id = ? AND status = 'submitted'", [$paymentId, $b['id']]) : null;
            if ($pay) {
                $db->update('payments', $row, 'id = :id', ['id' => $pay['id']]);
            } else {
                // Close any other open submission and record the payment received directly.
                $db->run("UPDATE payments SET status = 'rejected', reviewed_at = ?, reviewed_by = ?, review_note = 'Superseded by verified payment' WHERE booking_id = ? AND status = 'submitted'", [now(), $actor['name'], $b['id']]);
                $db->insert('payments', $row + ['booking_id' => $b['id'], 'method' => 'recorded_by_admin', 'submitted_at' => now()]);
            }
            $this->setStatus($cur, ['payment_status' => 'paid', 'payment_ref' => $ref, 'paid_at' => now()]);
            $this->audit->log($actor, 'payment', $ref, (int) $b['id'], 'payment.verified', [
                'reference' => $ref, 'amount' => $amount, 'due' => (int) $cur['total_paise'], 'note' => $note,
                'previous_status' => $cur['payment_status'],
            ]);
        });
        $fresh = $this->bookings->find((int) $b['id']);
        $this->notifier->statusUpdate($fresh, 'Your payment of ' . money($amount) . ' (ref ' . $ref . ') has been verified.' . ($fresh['booking_status'] === 'confirmed' ? ' Your booking is confirmed.' : ' Your booking is awaiting approval by the temple office.'));
        return $fresh;
    }

    /**
     * Gateway payment whose signature has already been verified server-side.
     * Idempotent: the browser callback and the webhook may both arrive.
     */
    public function markGatewayPaid(array $payment, string $gatewayPaymentId, string $source): ?array
    {
        $bookingId = (int) $payment['booking_id'];
        $changed = $this->db->transaction(function (Database $db) use ($payment, $gatewayPaymentId, $source, $bookingId) {
            $cur = $this->lock($bookingId);
            $row = $db->one('SELECT * FROM payments WHERE id = ?' . $db->forUpdate(), [(int) $payment['id']]);
            if (!$row || $row['status'] === 'verified' || $cur['payment_status'] === 'paid') {
                return false;
            }
            $db->update('payments', [
                'status' => 'verified', 'gateway_payment_id' => $gatewayPaymentId, 'reference' => $gatewayPaymentId,
                'reviewed_at' => now(), 'reviewed_by' => 'system:gateway', 'review_note' => 'Verified by gateway signature (' . $source . ')',
            ], 'id = :id', ['id' => (int) $row['id']]);
            if ((int) $row['amount_paise'] !== (int) $cur['total_paise']) {
                // Amount changed after the order was created: keep for manual review.
                $this->audit->log(Audit::system('gateway'), 'payment', $gatewayPaymentId, $bookingId, 'payment.gateway_amount_mismatch', ['paid' => (int) $row['amount_paise'], 'due' => (int) $cur['total_paise']]);
                $this->setStatus($cur, ['payment_status' => 'awaiting_verification', 'payment_ref' => $gatewayPaymentId]);
                return true;
            }
            $this->setStatus($cur, ['payment_status' => 'paid', 'payment_ref' => $gatewayPaymentId, 'paid_at' => now()]);
            $this->audit->log(Audit::system('gateway'), 'payment', $gatewayPaymentId, $bookingId, 'payment.verified', ['reference' => $gatewayPaymentId, 'amount' => (int) $row['amount_paise'], 'source' => $source]);
            return true;
        });
        $fresh = $this->bookings->find($bookingId);
        if ($changed && $fresh['payment_status'] === 'paid') {
            $this->notifier->statusUpdate($fresh, 'Your online payment of ' . money((int) $fresh['total_paise']) . ' has been received.' . ($fresh['booking_status'] === 'confirmed' ? ' Your booking is confirmed.' : ' Your booking is awaiting approval by the temple office.'));
        }
        return $fresh;
    }

    public function rejectPayment(array $b, array $actor, string $reason): array
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'Please explain why the payment could not be verified. This is shared with the devotee.']);
        }
        return $this->change($b, $actor, 'payment.rejected', function (array $cur, Database $db) use ($actor, $reason) {
            if ($cur['payment_status'] !== 'awaiting_verification') {
                throw new ValidationException(['action' => 'There is no submitted payment waiting for verification.']);
            }
            $db->run("UPDATE payments SET status = 'rejected', reviewed_at = ?, reviewed_by = ?, review_note = ? WHERE booking_id = ? AND status = 'submitted'", [now(), $actor['name'], $reason, $cur['id']]);
            return ['payment_status' => 'failed'];
        }, ['reason' => $reason], fn ($n) => 'We could not verify your payment. Reason: ' . $reason . ' Please check and submit the correct UPI reference using your booking link, or contact the temple office.');
    }

    public function refund(array $b, array $actor, string $referenceInput, string $note): array
    {
        $ref = Validator::text($referenceInput, 60);
        if ($ref === '') {
            throw new ValidationException(['reference' => 'Enter the refund transaction reference.']);
        }
        return $this->change($b, $actor, 'payment.refunded', function (array $cur, Database $db) use ($actor, $ref, $note) {
            if ($cur['payment_status'] !== 'paid' || !in_array($cur['booking_status'], ['cancelled', 'rejected'], true)) {
                throw new ValidationException(['action' => 'Refunds can be recorded only for paid bookings that are cancelled or not approved.']);
            }
            $db->run("UPDATE payments SET status = 'refunded', review_note = ? WHERE booking_id = ? AND status = 'verified'", ['Refund ref ' . $ref . ($note ? ': ' . $note : ''), $cur['id']]);
            return ['payment_status' => 'refunded'];
        }, ['refund_reference' => $ref, 'note' => $note], fn ($n) => 'A refund of ' . money((int) $n['total_paise']) . ' has been processed (ref ' . $ref . ').');
    }

    /** Change the per-booking additional charge (e.g. quote for "Other Temple Services"). */
    public function adjustCharges(array $b, array $actor, string $label, string $amountInput, string $note): array
    {
        $amount = parse_rupees($amountInput);
        if ($amount === null) {
            throw new ValidationException(['amount' => 'Enter a valid amount in rupees (0 to remove).']);
        }
        $label = Validator::text($label, 100) ?: 'Additional charges';
        return $this->change($b, $actor, 'booking.charges_adjusted', function (array $cur) use ($amount, $label) {
            if (!in_array($cur['payment_status'], ['pending', 'failed', 'not_required'], true) || !in_array($cur['booking_status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['action' => 'Charges can be changed only before a payment is submitted or verified.']);
            }
            $total = (int) $cur['subtotal_paise'] + $amount;
            return [
                'additional_paise' => $amount,
                'additional_label' => $amount > 0 ? $label : null,
                'total_paise' => $total,
                'payment_status' => $total > 0 ? 'pending' : 'not_required',
            ];
        }, ['amount' => $amount, 'label' => $label, 'note' => $note, 'previous_total' => (int) $b['total_paise']],
            fn ($n) => 'The amount payable for your booking has been updated to ' . money((int) $n['total_paise']) . '.' . ($note ? ' Note: ' . $note : '') . ((int) $n['total_paise'] > 0 ? ' Please pay using your booking link.' : ''));
    }

    public function addNote(array $b, array $actor, string $note): void
    {
        $note = Validator::text($note, 1000, true);
        if ($note === '') {
            throw new ValidationException(['note' => 'The note is empty.']);
        }
        $this->audit->log($actor, 'booking', $b['booking_no'], (int) $b['id'], 'booking.note', ['note' => $note]);
    }

    // ------------------------------------------------------------------ helpers

    private function lock(int|string $id): array
    {
        $row = $this->db->one('SELECT * FROM bookings WHERE id = ?' . $this->db->forUpdate(), [(int) $id]);
        if (!$row) {
            throw new ValidationException(['action' => 'Booking not found.']);
        }
        return $row;
    }

    /** Update a locked booking row, re-deriving booking_status. */
    private function setStatus(array $cur, array $changes): array
    {
        $next = array_merge($cur, $changes);
        $next['booking_status'] = $changes['booking_status'] ?? Status::derive($next['payment_status'], $next['approval_status'], $cur['booking_status']);
        $changes['booking_status'] = $next['booking_status'];
        $changes['updated_at'] = now();
        $this->db->update('bookings', $changes, 'id = :id', ['id' => (int) $cur['id']]);
        return $next;
    }

    /**
     * Generic guarded transition: $fn(currentRow, db) returns column changes.
     * Audits old/new status and optionally notifies the devotee with $message(newRow).
     */
    private function change(array $b, array $actor, string $action, callable $fn, array $details, ?callable $message): array
    {
        $this->db->transaction(function (Database $db) use ($b, $actor, $action, $fn, $details) {
            $cur = $this->lock($b['id']);
            $changes = $fn($cur, $db);
            $next = $this->setStatus($cur, $changes);
            $this->audit->log($actor, 'booking', $cur['booking_no'], (int) $cur['id'], $action, $details + [
                'from' => array_intersect_key($cur, array_flip(['booking_status', 'payment_status', 'approval_status', 'total_paise'])),
                'to' => array_intersect_key($next, array_flip(['booking_status', 'payment_status', 'approval_status', 'total_paise'])),
            ]);
        });
        $fresh = $this->bookings->find((int) $b['id']);
        if ($message) {
            $this->notifier->statusUpdate($fresh, $message($fresh));
        }
        return $fresh;
    }
}
