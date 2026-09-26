<div class="panel">
  <h3><?= e(t('reports.title')) ?></h3>
  <p style="color:var(--ink-soft); font-size:13.5px;"><?= e(t('reports.intro')) ?></p>
  <div class="kpi-grid">
    <a href="<?= e(url('reports/donations')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--gold);">
      <div style="font-size:28px;">🙏</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.donations')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.donations_note')) ?></div>
    </a>
    <a href="<?= e(url('reports/expenses')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--red);">
      <div style="font-size:28px;">💳</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.expenses')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.expenses_note')) ?></div>
    </a>
    <a href="<?= e(url('reports/inventory')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--navy);">
      <div style="font-size:28px;">📦</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.inventory')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.inventory_note')) ?></div>
    </a>
    <a href="<?= e(url('reports/food')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--green);">
      <div style="font-size:28px;">🌾</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.food')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.food_note')) ?></div>
    </a>
    <a href="<?= e(url('reports/vastra')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--blue);">
      <div style="font-size:28px;">🧵</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.vastra')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.vastra_note')) ?></div>
    </a>
    <a href="<?= e(url('reports/reconciliation')) ?>" class="panel" style="text-decoration:none; box-shadow:var(--shadow); border-top:3px solid var(--amber);">
      <div style="font-size:28px;">🏦</div>
      <div style="font-weight:700; color:var(--maroon-deep); margin-top:6px;"><?= e(t('reports.bank')) ?></div>
      <div style="color:var(--ink-soft); font-size:12.5px;"><?= e(t('reports.bank_note')) ?></div>
    </a>
  </div>
</div>
