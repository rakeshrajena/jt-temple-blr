<div class="panel">
  <h3>Available Reports</h3>
  <p style="color:var(--ink-soft); font-size:13.5px;">Every report opens in a print-friendly view with a Print button — use your browser's Print dialog to save as PDF or send to a printer.</p>
  <div class="kpi-grid">
    <a href="<?= e(url('reports/donations')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--gold);">
      <div style="font-size:28px;">🙏</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Donation Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">All donations, filterable by date range</div>
    </a>
    <a href="<?= e(url('reports/expenses')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--red);">
      <div style="font-size:28px;">💳</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Expense Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">All expenses, filterable by date range</div>
    </a>
    <a href="<?= e(url('reports/inventory')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--navy);">
      <div style="font-size:28px;">📦</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Inventory Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">Full hardware/general item listing</div>
    </a>
    <a href="<?= e(url('reports/food')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--green);">
      <div style="font-size:28px;">🌾</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Food Stock Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">Current raw food stock levels</div>
    </a>
    <a href="<?= e(url('reports/vastra')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--blue);">
      <div style="font-size:28px;">🧵</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Deity Vastra Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">All cloths/vastra by deity</div>
    </a>
    <a href="<?= e(url('reports/reconciliation')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--amber);">
      <div style="font-size:28px;">🏦</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;">Reconciliation Report</div>
      <div style="color:var(--ink-soft); font-size:12.5px;">Bank transactions matched/unmatched</div>
    </a>
  </div>
</div>
