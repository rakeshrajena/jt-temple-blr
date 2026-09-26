<?php
/** @var list<array<string, mixed>> $rows */
$me = (int) ($currentUser['id'] ?? 0);
$role = (string) ($currentUser['role'] ?? '');
$canDecide = $role === 'Admin' || $role === 'Treasurer';
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
  <h3>Approval queue</h3>
  <p class="sub">Staff prepare an item. A Treasurer can approve up to ₹10,000. Above that, an Admin decides. The person who prepared it cannot approve it. Only an approved line changes the cash book, day book, ledger, bank match, or stock. A write-off above <?= e((string) STOCK_WRITE_OFF_LIMIT) ?> units waits here even when the money amount is zero.</p>
  <?php if (!$rows): ?>
    <p>Nothing is waiting.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th>Item</th><th>Amount</th><th>Prepared by</th><th>Status</th><th>Decision</th></tr>
    <?php foreach ($rows as $row): ?>
      <?php
        $status = (string) $row['status'];
        $mine = $me === (int) $row['prepared_by'];
        $open = $status === 'Waiting' && $canDecide && !$mine;
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
                <button class="btn btn-sm btn-primary" type="submit" name="decision" value="approve">Approve</button>
                <button class="btn btn-sm btn-outline" type="submit" name="decision" value="send_back">Send back</button>
                <button class="btn btn-sm btn-outline" type="submit" name="decision" value="reject">Reject</button>
              </div>
            </form>
          <?php elseif ($mine && $status === 'Draft'): ?>
            <form method="POST" action="<?= e(url('approvals/' . $row['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit" name="decision" value="submit">Submit</button></form>
          <?php elseif ($mine && $status === 'Sent back'): ?>
            <form method="POST" action="<?= e(url('approvals/' . $row['id'])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit" name="decision" value="resubmit">Submit again</button></form>
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
