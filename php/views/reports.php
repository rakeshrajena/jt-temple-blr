<div class="panel">
  <h3><?= e(t('reports.title')) ?></h3>
  <p class="sub"><?= e(t('reports.intro')) ?></p>
  <div class="report-grid">
    <a class="report-card" href="<?= e(url('reports/donations')) ?>" style="border-top-color: var(--gold);">
      <strong><?= e(t('reports.donations')) ?></strong>
      <span><?= e(t('reports.donations_note')) ?></span>
    </a>
    <a class="report-card" href="<?= e(url('reports/expenses')) ?>" style="border-top-color: var(--red);">
      <strong><?= e(t('reports.expenses')) ?></strong>
      <span><?= e(t('reports.expenses_note')) ?></span>
    </a>
    <a class="report-card" href="<?= e(url('reports/inventory')) ?>" style="border-top-color: var(--navy);">
      <strong><?= e(t('reports.inventory')) ?></strong>
      <span><?= e(t('reports.inventory_note')) ?></span>
    </a>
    <a class="report-card" href="<?= e(url('reports/food')) ?>" style="border-top-color: var(--green);">
      <strong><?= e(t('reports.food')) ?></strong>
      <span><?= e(t('reports.food_note')) ?></span>
    </a>
    <a class="report-card" href="<?= e(url('reports/vastra')) ?>" style="border-top-color: var(--blue);">
      <strong><?= e(t('reports.vastra')) ?></strong>
      <span><?= e(t('reports.vastra_note')) ?></span>
    </a>
    <a class="report-card" href="<?= e(url('reports/reconciliation')) ?>" style="border-top-color: var(--amber);">
      <strong><?= e(t('reports.bank')) ?></strong>
      <span><?= e(t('reports.bank_note')) ?></span>
    </a>
  </div>
</div>
