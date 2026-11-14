<div class="panel reports-hub">
  <header class="reports-hub-header">
    <h3 class="reports-hub-title"><?= e(t('reports.title')) ?><?= help_tip(t('reports.intro')) ?></h3>
    <p class="reports-hub-intro"><?= e(t('reports.intro')) ?></p>
  </header>
  <div class="report-grid">
    <a class="report-card report-card--donations" href="<?= e(url('reports/donations')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.donations')) ?></span>
      <strong><?= e(t('reports.donations')) ?></strong>
      <span><?= e(t('reports.donations_note')) ?></span>
    </a>
    <a class="report-card report-card--expenses" href="<?= e(url('reports/expenses')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.expenses')) ?></span>
      <strong><?= e(t('reports.expenses')) ?></strong>
      <span><?= e(t('reports.expenses_note')) ?></span>
    </a>
    <a class="report-card report-card--inventory" href="<?= e(url('reports/inventory')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.inventory')) ?></span>
      <strong><?= e(t('reports.inventory')) ?></strong>
      <span><?= e(t('reports.inventory_note')) ?></span>
    </a>
    <a class="report-card report-card--food" href="<?= e(url('reports/food')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.food')) ?></span>
      <strong><?= e(t('reports.food')) ?></strong>
      <span><?= e(t('reports.food_note')) ?></span>
    </a>
    <a class="report-card report-card--vastra" href="<?= e(url('reports/vastra')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.vastra')) ?></span>
      <strong><?= e(t('reports.vastra')) ?></strong>
      <span><?= e(t('reports.vastra_note')) ?></span>
    </a>
    <a class="report-card report-card--bank" href="<?= e(url('reports/reconciliation')) ?>">
      <span class="report-card-kicker"><?= e(t('nav.bank')) ?></span>
      <strong><?= e(t('reports.bank')) ?></strong>
      <span><?= e(t('reports.bank_note')) ?></span>
    </a>
  </div>
</div>