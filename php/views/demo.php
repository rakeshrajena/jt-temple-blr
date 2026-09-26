<?php
/** @var array $summary */
/** @var list<array<string,mixed>> $recentDonations */
/** @var list<array<string,mixed>> $recentInvoices */
/** @var list<array<string,mixed>> $inventorySample */
/** @var list<array<string,mixed>> $foodSample */
/** @var list<array<string,mixed>> $vastraSample */
/** @var list<array<string,mixed>> $expenseSample */
/** @var list<array<string,mixed>> $bankSample */
/** @var string $generatedOn */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — Admin System Overview</title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=2">
  <style>
    body { background: var(--bg); }
    .demo-header { background: linear-gradient(135deg, var(--maroon-deep), var(--maroon)); color: #fff; padding: 36px 40px; text-align: center; }
    .demo-header img { width: 64px; height: 64px; margin-bottom: 10px; }
    .demo-header h1 { margin: 0 0 4px; font-size: 26px; }
    .demo-header p { margin: 0; opacity: 0.85; font-size: 14px; }
    .demo-wrap { max-width: 1300px; margin: 0 auto; padding: 30px 24px 60px; }
    .demo-section-title { font-size: 20px; color: var(--maroon-deep); font-weight: 800; margin: 34px 0 14px; padding-bottom: 8px; border-bottom: 3px solid var(--gold); display: flex; align-items: center; gap: 10px; }
    .demo-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .demo-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    @media (max-width: 900px) { .demo-grid-2, .demo-grid-3 { grid-template-columns: 1fr; } }
    .flow-strip { display: flex; align-items: center; gap: 10px; margin: 18px 0; flex-wrap: wrap; }
    .flow-step { background: #fff; border: 1.5px solid var(--border); border-radius: 10px; padding: 12px 16px; flex: 1; min-width: 150px; text-align: center; box-shadow: var(--shadow); }
    .flow-step .n { width: 26px; height: 26px; border-radius: 50%; background: var(--maroon); color: #fff; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; }
    .flow-arrow { color: var(--gold); font-size: 22px; }
    .demo-footer { text-align: center; padding: 24px; color: var(--ink-soft); font-size: 12.5px; }
  </style>
</head>
<body>
  <div class="demo-header">
    <img src="<?= e(asset('logo.svg')) ?>" alt="Temple Logo">
    <h1><?= e(APP_NAME) ?> — Admin Management System</h1>
    <p>Live Data Overview &nbsp;•&nbsp; <?= e(APP_PLACE) ?> &nbsp;•&nbsp; Generated <?= e($generatedOn) ?></p>
    <a href="<?= e(url('')) ?>" style="display:inline-block; margin-top:10px; color:#fff; opacity:0.75; font-size:12px; text-decoration:underline;">← Back to Admin Panel</a>
  </div>
  <div class="demo-wrap">
    <div class="demo-section-title">📊 At a Glance</div>
    <div class="kpi-grid">
      <div class="kpi-card good"><div class="value"><?= e(money($summary['total_donations'])) ?></div><div class="label">Total Donations Received</div></div>
      <div class="kpi-card danger"><div class="value"><?= e(money($summary['total_expenses'])) ?></div><div class="label">Total Expenses</div></div>
      <div class="kpi-card <?= $summary['net_balance'] >= 0 ? 'good' : 'danger' ?>"><div class="value"><?= e(money($summary['net_balance'])) ?></div><div class="label">Net Balance</div></div>
      <div class="kpi-card"><div class="value"><?= e((string) $summary['donor_count']) ?></div><div class="label">Unique Donors</div></div>
      <div class="kpi-card good"><div class="value"><?= e(money($summary['mrr'])) ?></div><div class="label">Monthly Recurring Revenue</div></div>
      <div class="kpi-card <?= $summary['pending_invoices'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['pending_invoices']) ?></div><div class="label">Subscription Invoices Due</div></div>
      <div class="kpi-card"><div class="value"><?= e((string) $summary['inventory_count']) ?></div><div class="label">Inventory Items Tracked</div></div>
      <div class="kpi-card <?= $summary['low_stock'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['low_stock']) ?></div><div class="label">Food Items Low on Stock</div></div>
    </div>

    <div class="demo-section-title">🔔 Monthly Subscriptions — Invoice → Payment Link → Auto-Reconciled</div>
    <p style="color:var(--ink-soft); font-size:13.5px; margin-top:-6px;">Devotees get billed each cycle. An SMS with a secure payment link goes to their registered mobile — when they pay, it is recorded here and mirrored into Donations.</p>
    <div class="flow-strip">
      <div class="flow-step"><div class="n">1</div>Invoice generated</div>
      <div class="flow-arrow">→</div>
      <div class="flow-step"><div class="n">2</div>SMS sent with pay link</div>
      <div class="flow-arrow">→</div>
      <div class="flow-step"><div class="n">3</div>Devotee taps &amp; pays</div>
      <div class="flow-arrow">→</div>
      <div class="flow-step"><div class="n">4</div>Auto-recorded &amp; reconciled</div>
    </div>
    <div class="panel">
      <table class="data-table">
        <tr><th>Subscriber</th><th>Plan</th><th>Amount</th><th>Period</th><th>Status</th></tr>
        <?php foreach ($recentInvoices as $i): ?>
        <tr>
          <td><?= e($i['subscriber_name']) ?></td>
          <td><?= e($i['plan_name']) ?></td>
          <td><?= e(money($i['amount'])) ?></td>
          <td><?= e($i['period_label']) ?></td>
          <td>
            <?php if ($i['status'] === 'Paid'): ?><span class="badge badge-green">Paid</span>
            <?php elseif ($i['status'] === 'Sent'): ?><span class="badge badge-blue">Sent</span>
            <?php elseif ($i['status'] === 'Overdue'): ?><span class="badge badge-red">Overdue</span>
            <?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="demo-section-title">🙏 Recent Donations</div>
    <div class="panel">
      <table class="data-table">
        <tr><th>Date</th><th>Donor</th><th>Type</th><th>Amount</th><th>Purpose</th><th>Receipt</th></tr>
        <?php foreach ($recentDonations as $d): ?>
        <tr>
          <td><?= e($d['donation_date']) ?></td><td><?= e($d['donor_name']) ?></td><td><?= e($d['donation_type']) ?></td>
          <td><?= e(money_or_dash($d['amount'])) ?></td><td><?= e(dash($d['purpose'])) ?></td>
          <td><?php if ((int) $d['receipt_generated'] === 1): ?><?= receipt_link($d['receipt_number']) ?><?= receipt_cancel_badge($d['receipt_cancelled'] ?? 0) ?><?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="demo-section-title">📦 Inventory, Food Stock &amp; Deity Vastra</div>
    <div class="demo-grid-3">
      <div class="panel">
        <h3>Inventory (top items)</h3>
        <table class="data-table">
          <tr><th>Item</th><th>Qty</th></tr>
          <?php foreach ($inventorySample as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= e($i['quantity']) ?> <?= e($i['unit']) ?></td></tr><?php endforeach; ?>
        </table>
      </div>
      <div class="panel">
        <h3>Food Stock</h3>
        <table class="data-table">
          <tr><th>Item</th><th>Stock</th><th>Status</th></tr>
          <?php foreach ($foodSample as $f): ?>
          <tr>
            <td><?= e($f['name']) ?></td>
            <td><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></td>
            <td><?php if ((float) $f['current_stock'] <= (float) $f['minimum_threshold']): ?><span class="badge badge-red">Low</span><?php else: ?><span class="badge badge-green">OK</span><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="panel">
        <h3>Deity Vastra</h3>
        <table class="data-table">
          <tr><th>Deity</th><th>Item</th><th>Status</th></tr>
          <?php foreach ($vastraSample as $v): ?><tr><td><?= e($v['deity_name']) ?></td><td><?= e($v['item_name']) ?></td><td><?= e($v['status']) ?></td></tr><?php endforeach; ?>
        </table>
      </div>
    </div>

    <div class="demo-section-title">💳 Expenses &amp; 🏦 Bank Reconciliation</div>
    <div class="demo-grid-2">
      <div class="panel">
        <h3>Recent Expenses</h3>
        <table class="data-table">
          <tr><th>Category</th><th>Description</th><th>Amount</th></tr>
          <?php foreach ($expenseSample as $e): ?><tr><td><?= e($e['category']) ?></td><td><?= e(dash($e['description'])) ?></td><td><?= e(money($e['amount'])) ?></td></tr><?php endforeach; ?>
        </table>
      </div>
      <div class="panel">
        <h3>Bank Reconciliation</h3>
        <?php if ($bankSample): ?>
        <table class="data-table">
          <tr><th>Description</th><th>Amount</th><th>Status</th></tr>
          <?php foreach ($bankSample as $t): ?>
          <tr>
            <td><?= e($t['description']) ?></td>
            <td><?= e(money($t['amount'])) ?></td>
            <td><?php if (in_array($t['reconciled_status'], ['Matched', 'Manual'], true)): ?><span class="badge badge-green">Matched</span><?php else: ?><span class="badge badge-amber">Review</span><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color:var(--ink-soft); font-size:13px;">Upload a bank statement in the live app to see auto-reconciliation in action.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="demo-footer">
    <?= e(APP_NAME) ?> Admin Management System — built for governance, transparency, and effortless reporting.<br>
    This is a live snapshot of application data.
  </div>
</body>
</html>
