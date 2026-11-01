<?php
/** @var list<array<string,mixed>> $bookingServices @var array<int,list<array<string,mixed>>> $bookingSlotsByService @var bool $bookingPricesReviewed @var bool $bookingCatalogCanEdit */
?>
<article id="ob-services" class="settings-card settings-ob-card booking-services-panel">
  <header class="settings-ob-card-head settings-head-inline">
    <div>
      <h5><?= e(t('settings.ob_services')) ?><?= help_tip(t('settings.ob_services_hint')) ?></h5>
    </div>
    <?php if ($bookingCatalogCanEdit): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(booking_catalog_settings_service_url(0)) ?>"><?= e(t('settings.ob_service_add')) ?></a>
    <?php endif; ?>
  </header>
  <?php if (!$bookingPricesReviewed): ?>
    <div class="settings-ob-notice no-print">
      <?= e(t('settings.ob_prices_review')) ?>
      <?php if ($bookingCatalogCanEdit): ?>
        <form method="POST" action="<?= e(url('settings')) ?>" class="form-inline" style="display:inline;margin-left:8px;">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="booking_prices_confirm">
          <button class="btn btn-outline btn-sm" type="submit"><?= e(t('settings.ob_prices_confirm')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="choice-table-wrap settings-ob-table-wrap">
    <table class="choice-table settings-ob-table">
      <tr>
        <th><?= e(t('common.name')) ?></th>
        <th><?= e(t('common.amount')) ?></th>
        <th><?= e(t('settings.ob_service_rules')) ?></th>
        <th><?= e(t('settings.ob_service_slots')) ?></th>
        <th><?= e(t('common.status')) ?></th>
        <th></th>
      </tr>
      <?php foreach ($bookingServices as $s): ?>
        <tr>
          <td><strong><?= e($s['name']) ?></strong><?php if ($s['is_food_only']): ?><br><span class="muted small"><?= e(t('settings.ob_food_only_svc')) ?></span><?php endif; ?></td>
          <td class="num"><span class="settings-ob-price"><?php if ($s['is_food_only']): ?>—<?php elseif ($s['price_paise'] > 0): ?><?= e(booking_money_paise($s['price_paise'])) ?><?php else: ?><span class="muted"><?= e(t('settings.ob_on_request')) ?></span><?php endif; ?></span></td>
          <td><small class="muted"><?= e(implode(' · ', array_filter([
              $s['requires_approval'] ? t('settings.ob_rule_approval') : null,
              $s['collect_participants'] ? t('settings.ob_rule_participants', ['n' => (string) $s['max_participants']]) : null,
              $s['allow_food'] ? t('settings.ob_rule_food') : t('settings.ob_rule_no_food'),
              !$s['is_combinable'] ? t('settings.ob_rule_alone') : null,
              $s['daily_capacity'] !== null ? t('settings.ob_rule_cap', ['n' => (string) $s['daily_capacity']]) : null,
              $s['fields'] ? count($s['fields']) . ' ' . t('settings.ob_extra_fields') : null,
          ]))) ?></small></td>
          <td><small><?php foreach ($bookingSlotsByService[$s['id']] ?? [] as $sl): ?><?= e($sl['label']) ?> (<?= $sl['capacity'] ?? '∞' ?>)<?= $sl['is_active'] ? '' : ' off' ?><br><?php endforeach; ?></small></td>
          <td><?php if ($s['is_active']): ?><span class="badge badge-green"><?= e(t('settings.ob_bookable')) ?></span><?php else: ?><span class="badge badge-amber"><?= e(t('settings.ob_disabled')) ?></span><?php endif; ?></td>
          <td class="settings-ob-row-actions">
            <?php if ($bookingCatalogCanEdit): ?>
              <a class="btn btn-outline btn-sm" href="<?= e(booking_catalog_settings_service_url((int) $s['id'])) ?>"><?= e(t('common.update')) ?></a>
              <form method="POST" action="<?= e(url('settings')) ?>" class="settings-ob-inline-form" onsubmit="return confirm(<?= e(json_encode(t('settings.ob_service_remove_confirm'), JSON_UNESCAPED_UNICODE)) ?>);">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="booking_service_delete">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-outline btn-sm btn-danger" type="submit"><?= e(t('common.remove')) ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</article>
