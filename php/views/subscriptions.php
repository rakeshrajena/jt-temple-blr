<?php
/** @var list<array<string,mixed>> $subs */
/** @var list<array<string,mixed>> $invoices */
/** @var float $mrr */
/** @var float $pendingAmount */
/** @var array{country_code: string, template: string, smtp_ready: bool} $messaging */
/** @var array<int, array{from_status: string, to_status: string, changed_at: string, changed_by_name: string}> $statusChanges */

$subscriberFields = static function (string $prefix, array $s = []): void {
    $value = static fn (string $key, string $fallback = ''): string => e((string) ($s[$key] ?? $fallback));
    $suggest = static function (string $prefix, string $name, string $label, string $source, string $current, string $help = ''): void {
        ?>
        <div class="form-group">
          <label for="<?= e($prefix . '-' . $name) ?>"><?= e($label) ?><?= help_tip($help) ?></label>
          <div class="suggest">
            <input id="<?= e($prefix . '-' . $name) ?>" type="text" name="<?= e($name) ?>" value="<?= e($current) ?>" required autocomplete="off" data-suggest data-kind="line" data-source="<?= e($source) ?>" data-open="focus" data-limit="20">
            <div class="suggest-menu" hidden></div>
          </div>
        </div>
        <?php
    };
    ?>
    <div class="form-grid">
      <div class="form-group"><label for="<?= e($prefix) ?>-name"><?= e(t('common.name')) ?></label><input id="<?= e($prefix) ?>-name" type="text" name="name" value="<?= $value('name') ?>" maxlength="150" required></div>
      <div class="form-group"><label for="<?= e($prefix) ?>-mobile"><?= e(t('ui.contact_no')) ?></label><input id="<?= e($prefix) ?>-mobile" type="text" name="mobile" value="<?= $value('mobile') ?>" placeholder="9XXXXXXXXX" maxlength="15" required></div>
      <div class="form-group"><label for="<?= e($prefix) ?>-email"><?= e(t('ui.email_optional')) ?></label><input id="<?= e($prefix) ?>-email" type="email" name="email" value="<?= $value('email') ?>" maxlength="120"></div>
      <div class="form-group"><label for="<?= e($prefix) ?>-family"><?= e(t('ui.family_members')) ?></label><input id="<?= e($prefix) ?>-family" type="text" name="family_members" value="<?= $value('family_members') ?>" maxlength="300"></div>
      <div class="form-group"><label for="<?= e($prefix) ?>-gotra"><?= e(t('ui.gotra')) ?></label><input id="<?= e($prefix) ?>-gotra" type="text" name="gotra" value="<?= $value('gotra') ?>" maxlength="80"></div>
      <div class="form-group"><label for="<?= e($prefix) ?>-seva"><?= e(t('ui.seva_date')) ?></label><input id="<?= e($prefix) ?>-seva" type="date" name="seva_date" value="<?= $value('seva_date') ?>"></div>
      <?php $suggest($prefix, 'plan_name', t('common.plan'), 'subscriber-plans', (string) ($s['plan_name'] ?? '')); ?>
      <div class="form-group"><label for="<?= e($prefix) ?>-amount"><?= e(t('common.amount')) ?></label><input id="<?= e($prefix) ?>-amount" type="number" step="0.01" min="0.01" name="plan_amount" value="<?= $value('plan_amount') ?>" required></div>
      <?php $suggest($prefix, 'frequency', t('ui.billing_cycle'), 'subscriber-cycles', (string) ($s['frequency'] ?? '')); ?>
      <?php $suggest($prefix, 'status', t('common.status'), 'subscriber-statuses', (string) ($s['status'] ?? SUBSCRIBER_INVOICE_STATUS), t('ui.subscriber_status_help')); ?>
    </div>
    <?php
};
?>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($mrr)) ?></div><div class="label">Monthly Recurring Revenue (Active)</div></div>
  <div class="kpi-card warn"><div class="value"><?= e(money($pendingAmount)) ?></div><div class="label">Pending / Overdue Amount</div></div>
  <div class="kpi-card"><div class="value"><?= count($subs) ?></div><div class="label">Total Subscribers</div></div>
</div>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-subscriber"><?= e(t('ui.add_subscriber')) ?></button>
</div>
  <div class="panel reveal-panel" id="reveal-subscriber" hidden>
    <h3><?= e(t('ui.add_subscriber')) ?></h3>
    <form method="POST" action="<?= e(url('subscriptions')) ?>">
      <?= csrf_field() ?>
      <?php $subscriberFields('subscriber-new'); ?>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_subscriber')) ?></button></div>
    </form>
    <script type="application/json" id="subscriber-plans"><?= json_encode(array_map(static fn (string $name): array => ['name' => $name], selection_values('plans')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="subscriber-cycles"><?= json_encode(array_map(static fn (string $name): array => ['name' => $name], selection_values('billing_cycles')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="subscriber-statuses"><?= json_encode(array_map(static fn (string $name): array => ['name' => $name], selection_values('subscriber_statuses')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </div>
</div>
<div class="panel reveal-group subscriber-list">
    <h3>Subscribers (<?= count($subs) ?>)</h3>
    <table class="data-table">
      <tr><th><?= e(t('common.name')) ?></th><th><?= e(t('common.plan')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.due')) ?></th><th></th></tr>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td><?= e($s['name']) ?><br><span style="color:var(--ink-soft); font-size:12px;"><?= e($s['mobile']) ?><?php if (!empty($s['email'])): ?> · <?= e((string) $s['email']) ?><?php endif; ?></span>
          <?php if (!empty($s['family_members'])): ?><br><span style="color:var(--ink-soft); font-size:12px;"><?= e(t('ui.family_members')) ?>: <?= e((string) $s['family_members']) ?></span><?php endif; ?>
          <?php if (!empty($s['gotra'])): ?><br><span style="color:var(--ink-soft); font-size:12px;"><?= e(t('ui.gotra')) ?>: <?= e((string) $s['gotra']) ?></span><?php endif; ?>
          <?php if (!empty($s['seva_date'])): ?><br><span style="color:var(--ink-soft); font-size:12px;"><?= e(t('ui.seva_date')) ?>: <?= e(date('d-M-Y', strtotime((string) $s['seva_date']))) ?></span><?php endif; ?>
        </td>
        <td><?= e($s['plan_name']) ?></td>
        <td><?= e(money($s['plan_amount'])) ?> / <?= e($s['frequency']) ?></td>
        <td>
          <?php
          $statusTone = match ((string) $s['status']) {
              'Active' => 'badge-green',
              'Paused' => 'badge-amber',
              'Cancelled' => 'badge-red',
              default => 'badge-grey',
          };
          $change = $statusChanges[(int) $s['id']] ?? null;
          ?>
          <span class="badge <?= e($statusTone) ?>"><?= e((string) $s['status']) ?></span>
          <?php if ($change !== null): ?>
          <br><span class="subscriber-status-change"><?= e(t('ui.status_changed', [
              'from' => $change['from_status'],
              'to' => $change['to_status'],
              'name' => $change['changed_by_name'] !== '' ? $change['changed_by_name'] : '—',
              'date' => date('d-M-Y H:i', strtotime($change['changed_at'])),
          ])) ?></span>
          <?php endif; ?>
        </td>
        <td><?php if ((int) $s['due_count'] > 0): ?><span class="badge badge-amber"><?= e((string) $s['due_count']) ?> due</span><?php else: ?><span class="badge badge-green">Up to date</span><?php endif; ?></td>
        <td>
          <div class="subscriber-actions">
            <button class="btn btn-sm btn-outline" type="button" data-reveal="subscriber-edit-<?= e((string) $s['id']) ?>"><?= e(t('common.update')) ?></button>
            <?php if (subscriber_can_invoice((string) $s['status'])): ?>
            <form method="POST" action="<?= e(url('subscriptions/' . $s['id'] . '/generate_invoice')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline" type="submit">+ Invoice</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <tr class="reveal-panel subscriber-edit" id="subscriber-edit-<?= e((string) $s['id']) ?>" hidden>
        <td colspan="6">
          <form method="POST" action="<?= e(url('subscriptions/' . $s['id'] . '/update')) ?>">
            <?= csrf_field() ?>
            <h4><?= e(t('ui.update_subscriber')) ?> · <?= e((string) $s['name']) ?></h4>
            <?php $subscriberFields('subscriber-' . (int) $s['id'], $s); ?>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.update_subscriber')) ?></button></div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
</div>
<div class="panel">
  <h3>All Invoices (<?= count($invoices) ?>)<?= help_tip('+ Invoice creates a billing record. Send writes the message to the log. If outgoing mail is saved in Settings, it is also emailed. WhatsApp opens WhatsApp Web with the message filled in. Nothing is sent through a WhatsApp API. The devotee opens the payment link and pays. The invoice then shows Paid here, and the payment is copied into Donations.') ?></h3>
  <form method="POST" action="<?= e(url('subscriptions/bulk_send')) ?>" id="bulkSendForm">
    <?= csrf_field() ?>
    <div class="toolbar">
      <button class="btn btn-gold btn-sm" type="submit" id="bulkSendBtn" disabled>📲 Send Selected (<span id="selCount">0</span>)</button>
      <span style="color:var(--ink-soft); font-size:12.5px;">Select invoices below, or use the header checkbox to select all unpaid invoices.</span>
    </div>
    <table class="data-table">
      <tr>
        <th style="width:36px;"><input type="checkbox" id="selectAll"></th>
        <th><?= e(t('ui.invoice_no')) ?></th><th><?= e(t('overview.subscriber')) ?></th><th><?= e(t('ui.contact')) ?></th><th><?= e(t('common.period')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('pay.due_date')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.payment_link')) ?></th><th></th>
      </tr>
      <?php foreach ($invoices as $i): ?>
      <tr>
        <td>
          <?php if ($i['status'] !== 'Paid'): ?>
          <input type="checkbox" name="invoice_ids[]" value="<?= e((string) $i['id']) ?>" class="invoice-checkbox">
          <?php endif; ?>
        </td>
        <td><?= e($i['invoice_number']) ?></td>
        <td><?= e($i['subscriber_name']) ?></td>
        <td style="font-size:12px; color:var(--ink-soft);"><?= e($i['mobile']) ?><?php if (!empty($i['email'])): ?><br><?= e($i['email']) ?><?php endif; ?></td>
        <td><?= e($i['period_label']) ?></td>
        <td><?= e(money($i['amount'])) ?></td>
        <td><?= e($i['due_date']) ?></td>
        <td>
          <?php if ($i['status'] === 'Paid'): ?><span class="badge badge-green">Paid</span>
          <?php elseif ($i['status'] === 'Sent'): ?><span class="badge badge-blue">Sent</span>
          <?php elseif ($i['status'] === 'Overdue'): ?><span class="badge badge-red">Overdue</span>
          <?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?>
        </td>
        <td>
          <?php if ($i['status'] !== 'Paid'): ?>
          <a href="<?= e(url('pay/' . $i['payment_token'])) ?>" target="_blank" class="btn btn-sm btn-outline"><?= e(t('ui.preview_pay')) ?></a>
          <?php else: ?>
          <span style="color:var(--ink-soft); font-size:12px;">Ref: <?= e($i['payment_reference']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (in_array($i['status'], ['Pending', 'Overdue'], true)): ?>
          <button class="btn btn-sm btn-gold" type="submit" form="singleSend<?= e((string) $i['id']) ?>">📲 Send</button>
          <?php elseif ($i['status'] === 'Sent'): ?>
          <button class="btn btn-sm btn-outline" type="submit" form="singleSend<?= e((string) $i['id']) ?>"><?= e(t('ui.resend')) ?></button>
          <?php endif; ?>
          <?php
            $waText = fill_message_template($messaging['template'], [
                'name' => (string) $i['subscriber_name'],
                'period' => (string) $i['period_label'],
                'amount' => number_format((float) $i['amount'], 0),
                'link' => absolute_url('pay/' . $i['payment_token']),
                'invoice' => (string) $i['invoice_number'],
            ]);
            $waUrl = $i['status'] === 'Paid' ? null : whatsapp_web_url((string) $i['mobile'], $waText, $messaging['country_code']);
          ?>
          <?php if ($waUrl !== null): ?>
          <a class="btn btn-sm btn-outline" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"><?= e(t('common.whatsapp')) ?></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
  <?php foreach ($invoices as $i): ?>
    <?php if ($i['status'] !== 'Paid'): ?>
    <form method="POST" action="<?= e(url('subscriptions/invoice/' . $i['id'] . '/send')) ?>" id="singleSend<?= e((string) $i['id']) ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<script>
  const selectAll = document.getElementById('selectAll');
  const checkboxes = () => Array.from(document.querySelectorAll('.invoice-checkbox'));
  const bulkBtn = document.getElementById('bulkSendBtn');
  const selCount = document.getElementById('selCount');
  function updateBulkButton() {
    const checked = checkboxes().filter(c => c.checked).length;
    selCount.textContent = checked;
    bulkBtn.disabled = checked === 0;
  }
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      checkboxes().forEach(c => { c.checked = selectAll.checked; });
      updateBulkButton();
    });
    checkboxes().forEach(c => c.addEventListener('change', updateBulkButton));
    updateBulkButton();
  }
</script>
