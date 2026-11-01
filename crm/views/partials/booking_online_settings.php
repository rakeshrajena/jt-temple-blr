<?php
/** @var array<string, string> $bs @var array $benv @var bool $bsCanEdit @var string $bookingAdminUrl @var bool $prasadamCatalogEnabled @var bool $prasadamCanEdit @var list<array<string,mixed>> $prasadamCatalogRows @var bool $bookingSettingsEnabled @var bool $bookingCatalogCanEdit @var list<array<string,mixed>> $bookingServices @var array<int,list<array<string,mixed>>> $bookingSlotsByService @var bool $bookingPricesReviewed @var list<array<string,mixed>> $bookingBlocked @var list<array<string,mixed>> $bookingServicesActive @var string $bookingAvailDate @var list<array<string,mixed>> $bookingDayView */
$v = static fn (string $k): string => e((string) ($bs[$k] ?? ''));
$on = static fn (string $k): string => (($bs[$k] ?? '0') === '1') ? ' checked' : '';
$extra = number_format(((int) ($bs['additional_charge_paise'] ?? 0)) / 100, 2, '.', '');
$obNav = static function (string $href, string $label, string $navId = '') use ($bookingSettingsEnabled): void {
    if ($href === '#ob-services' || $href === '#ob-availability') {
        if (empty($bookingSettingsEnabled)) {
            return;
        }
    }
    $dataNav = $navId !== '' ? ' data-nav="' . e($navId) . '"' : '';
    echo '<a href="' . e($href) . '" class="settings-ob-nav-link"' . $dataNav . '>' . e($label) . '</a>';
};
$obPrasadamCount = 0;
foreach ($prasadamCatalogRows as $row) {
    if ((int) ($row['id'] ?? 0) > 0) {
        $obPrasadamCount++;
    }
}
$obServiceCount = count($bookingServices);
?>
<div id="ob-panel-top" class="settings-booking-panel settings-ob-shell" data-settings-ob>
  <header class="settings-ob-hero">
    <div class="settings-ob-hero-text">
      <h3><?= e(t('settings.online_booking')) ?></h3>
      <p class="muted"><?= e(t('settings.online_booking_intro')) ?></p>
      <?php if (!empty($bookingSettingsEnabled)): ?>
      <ul class="settings-ob-stats" aria-label="<?= e(t('settings.ob_stats_label')) ?>">
        <li><strong><?= (int) $obServiceCount ?></strong> <?= e(t('settings.ob_stat_services')) ?></li>
        <li><strong><?= (int) $obPrasadamCount ?></strong> <?= e(t('settings.ob_stat_prasadam')) ?></li>
      </ul>
      <?php endif; ?>
    </div>
    <?php if ($bookingAdminUrl !== ''): ?>
      <a class="btn btn-outline btn-sm settings-ob-hero-btn" href="<?= e($bookingAdminUrl) ?>" target="_blank" rel="noopener"><?= e(t('settings.ob_admin')) ?></a>
    <?php endif; ?>
  </header>

  <div class="settings-ob-layout">
    <aside class="settings-ob-nav" aria-label="<?= e(t('settings.online_booking_nav')) ?>">
      <p class="settings-ob-nav-title"><?= e(t('settings.ob_nav_title')) ?></p>
      <p class="settings-ob-nav-kicker"><?= e(t('settings.ob_group_catalog')) ?></p>
      <?php $obNav('#ob-prasadam', t('settings.ob_prasadam_menu'), 'prasadam'); ?>
      <?php $obNav('#ob-services', t('settings.ob_services'), 'services'); ?>
      <p class="settings-ob-nav-kicker"><?= e(t('settings.ob_group_schedule')) ?></p>
      <?php $obNav('#ob-availability', t('settings.ob_availability'), 'availability'); ?>
      <p class="settings-ob-nav-kicker"><?= e(t('settings.ob_group_site')) ?></p>
      <?php $obNav('#ob-contact', t('settings.ob_contact'), 'contact'); ?>
      <?php $obNav('#ob-rules', t('settings.ob_rules'), 'rules'); ?>
      <p class="settings-ob-nav-kicker"><?= e(t('settings.ob_group_integrations')) ?></p>
      <?php $obNav('#ob-payment', t('settings.ob_payment'), 'payment'); ?>
      <?php $obNav('#ob-whatsapp', t('settings.ob_whatsapp'), 'whatsapp'); ?>
      <p class="settings-ob-nav-kicker"><?= e(t('settings.ob_links')) ?></p>
      <a class="settings-ob-nav-link settings-ob-nav-muted" href="<?= e(url('food/coupons')) ?>"><?= e(t('settings.ob_printed_coupons')) ?></a>
    </aside>

    <div class="settings-ob-main">
      <section class="settings-ob-group" aria-labelledby="ob-grp-catalog">
        <div class="settings-ob-group-head">
          <h4 id="ob-grp-catalog" class="settings-ob-group-title"><?= e(t('settings.ob_group_catalog')) ?></h4>
          <p class="settings-ob-group-desc muted"><?= e(t('settings.ob_group_catalog_desc')) ?></p>
        </div>
        <?php
          $obLegacyPending = function_exists('online_booking_legacy_puja_purposes_pending')
              && online_booking_legacy_puja_purposes_pending(legacy_selection_puja_purposes());
        ?>
        <?php if ($obLegacyPending): ?>
          <p class="settings-ob-notice no-print" role="status"><?= e(t('settings.ob_legacy_import_pending')) ?></p>
        <?php endif; ?>

        <?php if ($prasadamCatalogEnabled): ?>
        <article id="ob-prasadam" class="settings-card settings-ob-card prasadam-catalog-panel">
          <header class="settings-ob-card-head">
            <h5><?= e(t('settings.ob_prasadam_menu')) ?><?= help_tip(t('settings.ob_prasadam_hint')) ?></h5>
            <p class="muted small"><?= e(t('settings.ob_prasadam_intro')) ?></p>
          </header>
          <?php if (!$prasadamCanEdit): ?>
            <p class="muted"><?= e(t('settings.ob_prasadam_readonly')) ?></p>
          <?php endif; ?>
          <div class="prasadam-catalog-grid">
            <?php
              $prasadamFormAction = url('settings');
              require __DIR__ . '/prasadam_catalog_forms.php';
            ?>
          </div>
        </article>
        <?php else: ?>
        <article id="ob-prasadam" class="settings-card settings-ob-card">
          <h5><?= e(t('settings.ob_prasadam_menu')) ?></h5>
          <p class="muted"><?= e(t('settings.ob_prasadam_missing_table')) ?></p>
        </article>
        <?php endif; ?>

        <?php if (!empty($bookingSettingsEnabled)): ?>
          <?php require __DIR__ . '/booking_services_panel.php'; ?>
        <?php endif; ?>
      </section>

      <?php if (!empty($bookingSettingsEnabled)): ?>
      <section class="settings-ob-group" aria-labelledby="ob-grp-schedule">
        <div class="settings-ob-group-head">
          <h4 id="ob-grp-schedule" class="settings-ob-group-title"><?= e(t('settings.ob_group_schedule')) ?></h4>
          <p class="settings-ob-group-desc muted"><?= e(t('settings.ob_group_schedule_desc')) ?></p>
        </div>
        <?php require __DIR__ . '/booking_availability_panel.php'; ?>
      </section>
      <?php endif; ?>

      <section class="settings-ob-group" aria-labelledby="ob-grp-site">
        <div class="settings-ob-group-head">
          <h4 id="ob-grp-site" class="settings-ob-group-title"><?= e(t('settings.ob_group_site')) ?></h4>
          <p class="settings-ob-group-desc muted"><?= e(t('settings.ob_group_site_desc')) ?></p>
        </div>

        <form id="ob-contact" method="POST" action="<?= e(url('settings')) ?>" class="settings-ob-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="booking_contact">
          <article class="settings-card settings-ob-card">
            <header class="settings-ob-card-head">
              <h5><?= e(t('settings.ob_contact')) ?><?= help_tip(t('settings.ob_contact_hint')) ?></h5>
            </header>
            <div class="form-grid cols-2">
              <div class="form-group full"><label><?= e(t('settings.ob_temple_name')) ?></label><input type="text" name="temple_name" value="<?= $v('temple_name') ?>" maxlength="200" required<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_phone')) ?></label><input type="text" name="temple_phone" value="<?= $v('temple_phone') ?>" maxlength="200"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_email')) ?></label><input type="email" name="temple_email" value="<?= $v('temple_email') ?>" maxlength="200"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_website')) ?></label><input type="text" name="temple_website" value="<?= $v('temple_website') ?>" maxlength="200"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_gstin')) ?></label><input type="text" name="temple_gstin" value="<?= $v('temple_gstin') ?>" maxlength="200"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group full"><label><?= e(t('settings.ob_address')) ?></label><textarea name="temple_address" rows="2" maxlength="1000"<?= $bsCanEdit ? '' : ' readonly' ?>><?= $v('temple_address') ?></textarea></div>
              <div class="form-group full"><label><?= e(t('settings.ob_terms')) ?></label><textarea name="terms_text" rows="3" maxlength="1000"<?= $bsCanEdit ? '' : ' readonly' ?>><?= $v('terms_text') ?></textarea></div>
            </div>
            <?php if ($bsCanEdit): ?>
            <footer class="settings-ob-form-footer"><button class="btn btn-primary" type="submit"><?= e(t('settings.ob_save_contact')) ?></button></footer>
            <?php endif; ?>
          </article>
        </form>

        <form id="ob-rules" method="POST" action="<?= e(url('settings')) ?>" class="settings-ob-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="booking_rules">
          <article class="settings-card settings-ob-card">
            <header class="settings-ob-card-head">
              <h5><?= e(t('settings.ob_rules')) ?><?= help_tip(t('settings.ob_rules_hint')) ?></h5>
              <?php if (!empty($bookingSettingsEnabled) && function_exists('booking_catalog_public_min_date')): ?>
                <p class="muted small"><?= e(t('settings.ob_public_window', [
                  'from' => booking_fmt_date(booking_catalog_public_min_date()),
                  'to' => booking_fmt_date(booking_catalog_public_max_date()),
                ])) ?></p>
              <?php endif; ?>
            </header>
            <div class="form-grid cols-3">
              <div class="form-group"><label><?= e(t('settings.ob_lead_days')) ?></label><input type="number" name="booking_lead_days" min="0" max="730" value="<?= $v('booking_lead_days') ?>" required<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_max_days')) ?></label><input type="number" name="booking_max_days" min="1" max="730" value="<?= $v('booking_max_days') ?>" required<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_booking_prefix')) ?></label><input type="text" name="booking_prefix" maxlength="8" value="<?= $v('booking_prefix') ?>" required<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_invoice_prefix')) ?></label><input type="text" name="invoice_prefix" maxlength="8" value="<?= $v('invoice_prefix') ?>" required<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_extra_label')) ?></label><input type="text" name="additional_charge_label" value="<?= $v('additional_charge_label') ?>" maxlength="200"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_extra_amount')) ?></label><input type="text" name="additional_charge" inputmode="decimal" value="<?= e($extra) ?>"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
            </div>
            <?php if ($bsCanEdit): ?>
            <footer class="settings-ob-form-footer"><button class="btn btn-primary" type="submit"><?= e(t('settings.ob_save_rules')) ?></button></footer>
            <?php endif; ?>
          </article>
        </form>
      </section>

      <section class="settings-ob-group" aria-labelledby="ob-grp-int">
        <div class="settings-ob-group-head">
          <h4 id="ob-grp-int" class="settings-ob-group-title"><?= e(t('settings.ob_group_integrations')) ?></h4>
          <p class="settings-ob-group-desc muted"><?= e(t('settings.ob_group_integrations_desc')) ?></p>
        </div>

        <article id="ob-payment" class="settings-card settings-ob-card settings-ob-card-info">
          <header class="settings-ob-card-head">
            <h5><?= e(t('settings.ob_payment')) ?><?= help_tip(t('settings.ob_payment_hint')) ?></h5>
          </header>
          <?php if ($benv['upi_manual']): ?>
            <p class="muted"><?= e(t('settings.ob_upi_manual_on')) ?></p>
            <?php if ($bookingAdminUrl !== ''): ?>
              <p><a class="btn btn-outline btn-sm" href="<?= e($bookingAdminUrl . '/settings#payment') ?>"><?= e(t('settings.ob_upi_form')) ?></a></p>
            <?php endif; ?>
          <?php else: ?>
            <p><?= e(t('settings.ob_razorpay_blurb')) ?></p>
            <p class="settings-ob-status-row">
              <span class="badge <?= $benv['razorpay_configured'] ? 'badge-green' : 'badge-amber' ?>">
                <?= e($benv['razorpay_configured'] ? t('settings.ob_razorpay_ok') : t('settings.ob_razorpay_missing')) ?>
              </span>
              <?php if ($benv['razorpay_key_hint'] !== ''): ?>
                <span class="muted small">Key ID <?= e($benv['razorpay_key_hint']) ?></span>
              <?php endif; ?>
            </p>
            <dl class="settings-ob-dl">
              <dt>Config</dt>
              <dd><code>Booking/.env</code> — <code>PAYMENT_GATEWAY</code>, <code>RAZORPAY_*</code></dd>
              <?php if (booking_razorpay_webhook_url() !== ''): ?>
                <dt><?= e(t('settings.ob_webhook')) ?></dt>
                <dd><code class="settings-ob-code-break"><?= e(booking_razorpay_webhook_url()) ?></code></dd>
              <?php endif; ?>
            </dl>
          <?php endif; ?>
        </article>

        <form id="ob-whatsapp" method="POST" action="<?= e(url('settings')) ?>" class="settings-ob-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="booking_whatsapp">
          <article class="settings-card settings-ob-card">
            <header class="settings-ob-card-head">
              <h5><?= e(t('settings.ob_whatsapp')) ?><?= help_tip(t('settings.ob_whatsapp_hint')) ?></h5>
              <p class="settings-ob-status-row">
                <span class="badge <?= $benv['wa_configured'] ? 'badge-green' : 'badge-amber' ?>">
                  <?= e($benv['wa_configured'] ? t('settings.ob_wa_ok') : t('settings.ob_wa_missing')) ?>
                </span>
                <span class="muted small"><?= e(t('settings.ob_wa_env')) ?></span>
              </p>
            </header>
            <?php if (booking_whatsapp_webhook_url() !== ''): ?>
              <p class="muted small"><?= e(t('settings.ob_wa_webhook')) ?>: <code class="settings-ob-code-break"><?= e(booking_whatsapp_webhook_url()) ?></code></p>
            <?php endif; ?>
            <div class="form-grid cols-2">
              <div class="form-group full">
                <label class="settings-check"><input type="checkbox" name="whatsapp_enabled" value="1"<?= $on('whatsapp_enabled') ?><?= $bsCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_wa_enable')) ?></label>
              </div>
              <div class="form-group full"><label><?= e(t('settings.ob_wa_admin_nums')) ?></label><input type="text" name="admin_whatsapp_numbers" value="<?= $v('admin_whatsapp_numbers') ?>" maxlength="500"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_wa_lang')) ?></label><input type="text" name="whatsapp_language" value="<?= $v('whatsapp_language') ?>" maxlength="10"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_tpl_ack')) ?></label><input type="text" name="wa_tpl_devotee_ack" value="<?= $v('wa_tpl_devotee_ack') ?>" maxlength="80"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_tpl_update')) ?></label><input type="text" name="wa_tpl_devotee_update" value="<?= $v('wa_tpl_devotee_update') ?>" maxlength="80"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_tpl_admin_new')) ?></label><input type="text" name="wa_tpl_admin_new" value="<?= $v('wa_tpl_admin_new') ?>" maxlength="80"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
              <div class="form-group"><label><?= e(t('settings.ob_tpl_admin_pay')) ?></label><input type="text" name="wa_tpl_admin_payment" value="<?= $v('wa_tpl_admin_payment') ?>" maxlength="80"<?= $bsCanEdit ? '' : ' readonly' ?>></div>
            </div>
            <?php if ($bsCanEdit): ?>
            <footer class="settings-ob-form-footer"><button class="btn btn-primary" type="submit"><?= e(t('settings.ob_save_whatsapp')) ?></button></footer>
            <?php endif; ?>
          </article>
        </form>
      </section>
    </div>
  </div>
</div>
