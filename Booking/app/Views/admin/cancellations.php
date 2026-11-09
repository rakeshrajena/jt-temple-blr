<?php
/**
 * @var string $date
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $elsewhere
 */
$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));
$render = function (array $list) use ($date, $q): void {
    if (!$list) {
        return;
    }
    echo '<table class="table compact"><thead><tr><th>Item</th><th>When</th><th class="num">Qty</th><th class="num">Amount</th><th>Line</th><th>Cancel</th></tr></thead><tbody>';
    foreach ($list as $b) {
        $open = in_array($b['booking_status'], ['pending', 'confirmed'], true) && ($b['cancellation_status'] ?? '') !== 'awaiting_approval';
        $paid = ($b['payment_status'] ?? '') === 'paid';
        echo '<tr><td colspan="6"><strong><a href="' . e(url('/admin/bookings/' . $b['booking_no'])) . '">' . e($b['booking_no']) . '</a></strong> · '
            . e($b['devotee_name']) . ' · ' . e(fmt_mobile($b['mobile']))
            . ' · ' . e(fmt_date($b['service_date']))
            . ' · ' . e(money((int) $b['total_paise']))
            . ' · ' . badge('booking', $b['booking_status']) . ' ' . badge('payment', $b['payment_status']);
        if ($paid) {
            echo '<br><small class="muted">Paid. Cancelling a line removes it from this day and frees the place. The amount already received stays until the whole booking is cancelled for a refund.</small>';
        }
        echo '</td></tr>';
        foreach ($b['items'] as $it) {
            $cancelled = !empty($it['cancelled_at']);
            $kind = $it['item_type'] === 'food' ? 'Prasad' : 'Service';
            echo '<tr><td>' . e($kind) . '<br><strong>' . e($it['name']) . '</strong></td>';
            echo '<td>' . e((string) ($it['slot_label'] ?: '-')) . '</td>';
            echo '<td class="num">' . (int) $it['quantity'] . '</td>';
            echo '<td class="num">' . e(money((int) $it['amount_paise'])) . '</td>';
            echo '<td>' . ($cancelled ? '<span class="badge badge-bad">Cancelled</span><br><small>' . e((string) ($it['cancel_reason'] ?? '')) . '</small>' : '<span class="badge badge-ok">Active</span>') . '</td><td>';
            if ($open && !$cancelled && $b['payment_status'] !== 'awaiting_verification') {
                echo '<form method="post" action="' . e(url('/admin/cancellations/cancel')) . '" class="day-cancel" data-confirm="Cancel ' . e($it['name']) . ' on ' . e($b['booking_no']) . '?">';
                echo csrf_field();
                echo '<input type="hidden" name="booking_no" value="' . e($b['booking_no']) . '">';
                echo '<input type="hidden" name="item_id" value="' . (int) $it['id'] . '">';
                echo '<input type="hidden" name="date" value="' . e($date) . '">';
                echo '<input type="hidden" name="q" value="' . e($q) . '">';
                echo '<input type="text" name="reason" required maxlength="255" placeholder="Reason for the devotee">';
                echo '<button class="btn btn-small btn-danger" type="submit">Cancel</button></form>';
            } elseif (!$cancelled && $b['payment_status'] === 'awaiting_verification') {
                echo '<small class="muted">Check the payment first</small>';
            } else {
                echo '-';
            }
            echo '</td></tr>';
        }
    }
    echo '</tbody></table>';
};
?>
<div class="row-between">
  <h1>Cancellations</h1>
  <p class="muted">Staff only. Cancel one service or one prasadam line. The devotee is notified.</p>
</div>
<form class="panel filters" method="get" action="<?= e(url('/admin/cancellations')) ?>">
  <label>Service date <input type="date" name="date" required value="<?= e($date) ?>"></label>
  <label class="grow">Booking number or phone <input name="q" value="<?= e($q) ?>" placeholder="SJT-2026-000001 or mobile number" inputmode="search"></label>
  <div class="filter-actions">
    <button class="btn btn-primary" type="submit">Show</button>
    <a href="<?= e(url('/admin/cancellations', ['date' => $prev, 'q' => $q])) ?>">Previous day</a>
    <a href="<?= e(url('/admin/cancellations', ['date' => today(), 'q' => $q])) ?>">Today</a>
    <a href="<?= e(url('/admin/cancellations', ['date' => $next, 'q' => $q])) ?>">Next day</a>
  </div>
</form>

<section class="panel table-wrap">
  <h2><?= e(fmt_date($date)) ?><?= $date === today() ? ' (today)' : '' ?></h2>
  <?php if (!$rows): ?>
    <p class="muted"><?= $q !== '' ? 'No booking on this date matches that booking number or phone.' : 'No bookings on this date.' ?></p>
  <?php endif; ?>
  <?php $render($rows); ?>
</section>

<?php if ($elsewhere): ?>
<section class="panel table-wrap">
  <h2>Same booking or phone on other dates</h2>
  <?php $render($elsewhere); ?>
</section>
<?php endif; ?>
