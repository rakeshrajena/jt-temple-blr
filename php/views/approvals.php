<?php
/** @var list<array<string, mixed>> $rows */
$me = (int) ($currentUser['id'] ?? 0);
$role = (string) ($currentUser['role'] ?? '');
$staffLimit = approval_limit('Staff');
$treasurerLimit = approval_limit('Treasurer');
$canDecide = $role === 'Admin' || $role === 'Treasurer' || ($role === 'Staff' && $staffLimit !== null && $staffLimit > 0);
$badge = static function (string $status): string {
    return match ($status) {
        'Approved' => 'badge-green',
        'Waiting' => 'badge-amber',
        'Sent back' => 'badge-blue',
        'Rejected' => 'badge-red',
        default => 'badge-grey',
    };
};
$label = static function (array $row): string {
    $type = (string) $row['subject_type'];
    if ($type === 'expense') {
        $voucher = (string) ($row['voucher_number'] ?? '');
        $who = (string) ($row['paid_to'] ?? '');
        return trim(($voucher !== '' ? $voucher . ' · ' : '') . (string) ($row['category'] ?? 'Expense') . ($who !== '' ? ' · ' . $who : '') . ' · ' . (string) ($row['expense_date'] ?? ''));
    }
    if ($type === 'contra') {
        return (string) ($row['direction'] ?? 'Contra') . ' · ' . (string) ($row['contra_date'] ?? '') . ((string) ($row['contra_note'] ?? '') !== '' ? ' · ' . (string) $row['contra_note'] : '');
    }
    if ($type === 'correction') {
        $target = (string) ($row['correction_target'] ?? 'line');
        return 'Correction · ' . $target
            . ' · was ' . money($row['original_amount'] ?? 0)
            . ' now ' . money($row['corrected_amount'] ?? 0)
            . ((string) ($row['correction_reason'] ?? '') !== '' ? ' · ' . (string) $row['correction_reason'] : '');
    }
    if ($type === 'receipt') {
        $number = (string) ($row['cancel_receipt'] ?? '');
        $donor = (string) ($row['cancel_donor'] ?? '');
        return 'Cancel receipt' . ($number !== '' ? ' ' . $number : '') . ($donor !== '' ? ' · ' . $donor : '')
            . ((string) ($row['cancel_reason'] ?? '') !== '' ? ' · ' . (string) $row['cancel_reason'] : '');
    }
    if ($type === 'purchase') {
        $qty = (string) ($row['purchase_qty'] ?? '');
        $name = (string) ($row['purchase_name'] ?? 'Purchase');
        return 'Purchase' . ($qty !== '' ? ' ' . $qty : '') . ' · ' . $name;
    }
    if ($type === 'stock') {
        $movement = (string) ($row['stock_movement'] ?? 'Write-off');
        $qty = (string) ($row['stock_qty'] ?? '');
        $name = (string) ($row['stock_name'] ?? '');
        return $movement . ($qty !== '' ? ' ' . $qty : '') . ($name !== '' ? ' · ' . $name : '');
    }
    if ($type === 'donation_edit') {
        $who = (string) ($row['edit_was_donor'] ?? $row['edit_donor'] ?? '');
        $reason = (string) ($row['edit_reason'] ?? '');
        $both = donation_edit_needs_both((string) ($row['edit_added_at'] ?? ''));
        $signed = [];
        if (!empty($row['edit_treasurer_name'])) {
            $signed[] = 'Treasurer ' . (string) $row['edit_treasurer_name'];
        }
        if (!empty($row['edit_admin_name'])) {
            $signed[] = 'Admin ' . (string) $row['edit_admin_name'];
        }
        return 'Edit donation' . ($who !== '' ? ' · ' . $who : '')
            . ' · was ' . money($row['edit_was_amount'] ?? 0)
            . ' now ' . money($row['edit_amount'] ?? 0)
            . ($both ? ' · needs Treasurer and Admin' : '')
            . ($signed !== [] ? ' · ' . implode(', ', $signed) : '')
            . ($reason !== '' ? ' · ' . $reason : '');
    }
    if ($type === 'coupon') {
        $name = (string) ($row['coupon_name'] ?? 'Coupons');
        $qty = (string) ($row['coupon_qty'] ?? '');
        $cost = isset($row['coupon_cost']) ? money($row['coupon_cost']) : '';
        return 'Coupons · ' . $name . ($qty !== '' ? ' · ' . $qty . ' × ' . $cost : '');
    }
    if ($type === 'opening') {
        $cash = $row['pending_cash'] !== null ? (float) $row['pending_cash'] : (float) ($row['cash_amount'] ?? 0);
        $bank = $row['pending_bank'] !== null ? (float) $row['pending_bank'] : (float) ($row['bank_amount'] ?? 0);
        return 'Opening ' . (string) ($row['financial_year'] ?? '') . ' · cash ' . money($cash) . ' · bank ' . money($bank);
    }
    return ucfirst($type);
};
?>
<div class="panel">
  <h3><?= e(t('ui.approval_queue')) ?></h3>
  <ol class="rules">
    <?php if ($staffLimit !== null && $staffLimit > 0): ?>
      <li>Staff can approve up to <?= e(money($staffLimit)) ?>.</li>
    <?php else: ?>
      <li>Staff prepare an item and cannot decide it.</li>
    <?php endif; ?>
    <li>A Treasurer can approve up to <?= e(money($treasurerLimit)) ?>.</li>
    <li>Above that, an Admin decides.</li>
    <li>The person who prepared it cannot approve it.</li>
    <li>A donation edit from the last 24 hours follows that limit.</li>
    <li>An older donation edit changes nothing until both a Treasurer and an Admin approve it, whatever the amount or the field.</li>
    <li>Only an approved line changes the cash book, day book, ledger, bank match, stock, or a donation.</li>
    <li>Approving a donation edit rewrites its receipt when one already exists.</li>
    <li>A write-off above <?= e((string) STOCK_WRITE_OFF_LIMIT) ?> units waits here even when the money amount is zero.</li>
  </ol>
  <?php if (!$rows): ?>
    <p>Nothing is waiting.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('common.amount')) ?></th><th>Prepared by</th><th><?= e(t('common.status')) ?></th><th>Decision</th></tr>
    <?php foreach ($rows as $row): ?>
      <?php
        $status = (string) $row['status'];
        $mine = $me === (int) $row['prepared_by'];
        $needsBoth = (string) $row['subject_type'] === 'donation_edit' && donation_edit_needs_both((string) ($row['edit_added_at'] ?? ''));
        $roleSigned = $needsBoth && (
            ($role === 'Treasurer' && !empty($row['edit_treasurer_by']))
            || ($role === 'Admin' && !empty($row['edit_admin_by']))
        );
        $open = $needsBoth
            ? $status === 'Waiting' && !$mine && ($role === 'Treasurer' || $role === 'Admin') && !$roleSigned
            : $status === 'Waiting' && $canDecide && !$mine;
      ?>
      <tr>
        <td><?= e($label($row)) ?></td>
        <td><?= e(money($row['amount'])) ?></td>
        <td><?= e((string) $row['prepared_name']) ?></td>
        <td>
          <span class="badge <?= e($badge($status)) ?>"><?= e($status) ?></span>
          <?php if (!empty($row['decision_note'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['decision_note']) ?></span><?php endif; ?>
        </td>
        <td>
          <?php if ($open): ?>
            <form method="POST" action="<?= e(url('approvals/' . $row['id'])) ?>">
              <?= csrf_field() ?>
              <input type="text" name="decision_note" maxlength="500" placeholder="Note, required to send back or reject">
              <div class="form-actions">
                <button class="btn btn-sm btn-primary" type="submit" name="decision" value="approve"><?= e(t('ui.approve')) ?></button>
                <button class="btn btn-sm btn-outline" type="submit" name="decision" value="send_back"><?= e(t('ui.send_back')) ?></button>
                <button class="btn btn-sm btn-outline" type="submit" name="decision" value="reject"><?= e(t('ui.reject')) ?></button>
              </div>
            </form>
          <?php elseif ($needsBoth && $roleSigned): ?>
            <?= $role === 'Treasurer' ? 'Waiting for an Admin' : 'Waiting for a Treasurer' ?>
          <?php elseif ($mine && $status === 'Draft'): ?>
            <form method="POST" action="<?= e(url('approvals/' . $row['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit" name="decision" value="submit"><?= e(t('ui.submit')) ?></button></form>
          <?php elseif ($mine && $status === 'Sent back'): ?>
            <form method="POST" action="<?= e(url('approvals/' . $row['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit" name="decision" value="resubmit"><?= e(t('ui.submit_again')) ?></button></form>
          <?php elseif ($status === 'Waiting' && $mine): ?>
            Waiting for someone else
          <?php else: ?>
            <?= e(dash((string) ($row['decided_name'] ?? ''))) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
