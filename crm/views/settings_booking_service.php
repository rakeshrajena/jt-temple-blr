<?php
/** @var array<string,mixed> $s @var list<array<string,mixed>> $slots @var bool $bookingCatalogCanEdit */
$chk = static fn (bool $v): string => $v ? ' checked' : '';
$fields = $s['fields'];
$extra = [];
for ($i = count($fields); $i < count($fields) + 3; $i++) {
    $extra[] = $i;
}
$wd = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'];
$back = url('settings') . '#ob-services';
$serviceAction = booking_catalog_settings_service_url((int) $s['id']);
?>
<div class="settings-page settings-ob-page">
  <p class="settings-ob-back"><a href="<?= e($back) ?>">← <?= e(t('settings.ob_services')) ?></a></p>
  <header class="settings-ob-hero">
    <div>
      <h2 style="margin:0;font-size:1.35rem;"><?= e($pageTitle) ?></h2>
    </div>
  </header>

  <form method="POST" action="<?= e($serviceAction) ?>" class="settings-ob-form">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="booking_service_save">
    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    <article class="settings-card settings-ob-card">
      <header class="settings-ob-card-head">
        <h5><?= e(t('settings.ob_service_rules')) ?></h5>
      </header>
      <div class="form-grid cols-2">
        <div class="form-group"><label><?= e(t('settings.ob_service_name')) ?></label><input name="name" required maxlength="150" value="<?= e($s['name']) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_service_price')) ?><?php if ($s['is_food_only']): ?> <span class="muted">(<?= e(t('settings.ob_food_only_svc')) ?>)</span><?php endif; ?></label>
          <?php if ($s['is_food_only']): ?>
            <input type="hidden" name="price" value="0">
            <input inputmode="decimal" value="0" readonly disabled aria-disabled="true">
          <?php else: ?>
            <input name="price" inputmode="decimal" value="<?= e(number_format($s['price_paise'] / 100, 2, '.', '')) ?>" required<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>>
          <?php endif; ?>
          <span class="muted small"><?= e(t('settings.ob_price_hint')) ?></span>
        </div>
        <div class="form-group full"><label><?= e(t('settings.ob_service_desc')) ?></label><textarea name="description" rows="2" maxlength="500"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>><?= e((string) $s['description']) ?></textarea></div>
      </div>
      <div class="settings-ob-rules-grid">
        <label class="settings-check"><input type="checkbox" name="is_active" value="1"<?= $chk($s['is_active']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_online')) ?></label>
        <label class="settings-check"><input type="checkbox" name="requires_approval" value="1"<?= $chk($s['requires_approval']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_approval')) ?></label>
        <label class="settings-check"><input type="checkbox" name="requires_slot" value="1"<?= $chk($s['requires_slot']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_slot')) ?></label>
        <label class="settings-check"><input type="checkbox" name="collect_participants" value="1"<?= $chk($s['collect_participants']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_ask_participants')) ?></label>
        <?php if (!$s['is_food_only']): ?>
        <label class="settings-check"><input type="checkbox" name="allow_food" value="1"<?= $chk($s['allow_food']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_food')) ?></label>
        <label class="settings-check"><input type="checkbox" name="is_combinable" value="1"<?= $chk($s['is_combinable']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_rule_combinable')) ?></label>
        <?php endif; ?>
      </div>
      <div class="form-grid cols-3">
        <div class="form-group"><label><?= e(t('settings.ob_max_participants')) ?></label><input type="number" name="max_participants" min="1" value="<?= (int) $s['max_participants'] ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_daily_cap')) ?></label><input type="number" name="daily_capacity" min="0" value="<?= e((string) ($s['daily_capacity'] ?? '')) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_lead_override')) ?></label><input type="number" name="lead_days" min="0" value="<?= e((string) ($s['lead_days'] ?? '')) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_prasadam_sort')) ?></label><input type="number" name="sort_order" value="<?= (int) $s['sort_order'] ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
      </div>
      <div class="settings-ob-subsection">
        <h6><?= e(t('settings.ob_extra_fields')) ?></h6>
        <p class="muted small"><?= e(t('settings.ob_extra_fields_hint')) ?></p>
        <div class="choice-table-wrap settings-ob-table-wrap">
          <table class="choice-table settings-ob-table compact">
            <tr><th><?= e(t('settings.label')) ?></th><th><?= e(t('settings.ob_field_type')) ?></th><th><?= e(t('settings.ob_required')) ?></th><th><?= e(t('settings.ob_options')) ?></th></tr>
            <?php foreach ($fields as $i => $fd): ?>
              <tr>
                <td><input type="hidden" name="field_key[<?= $i ?>]" value="<?= e($fd['key']) ?>"><input name="field_label[<?= $i ?>]" value="<?= e($fd['label']) ?>" maxlength="80"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></td>
                <td><select name="field_type[<?= $i ?>]"<?= $bookingCatalogCanEdit ? '' : ' disabled' ?>><?php foreach (BOOKING_FIELD_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= $fd['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
                <td><label class="settings-check"><input type="checkbox" name="field_required[<?= $i ?>]" value="1"<?= $chk(!empty($fd['required'])) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>></label></td>
                <td><input name="field_options[<?= $i ?>]" value="<?= e(implode(', ', $fd['options'] ?? [])) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></td>
              </tr>
            <?php endforeach; ?>
            <?php foreach ($extra as $i): ?>
              <tr>
                <td><input name="field_label[<?= $i ?>]" maxlength="80" placeholder="<?= e(t('settings.ob_new_field')) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></td>
                <td><select name="field_type[<?= $i ?>]"<?= $bookingCatalogCanEdit ? '' : ' disabled' ?>><?php foreach (BOOKING_FIELD_TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
                <td><label class="settings-check"><input type="checkbox" name="field_required[<?= $i ?>]" value="1"<?= $bookingCatalogCanEdit ? '' : ' disabled' ?>></label></td>
                <td><input name="field_options[<?= $i ?>]"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php if ($bookingCatalogCanEdit): ?>
      <footer class="settings-ob-form-footer"><button class="btn btn-primary" type="submit"><?= e(t('settings.ob_service_save')) ?></button></footer>
      <?php endif; ?>
    </article>
  </form>

  <?php if ($s['id']): ?>
  <article class="settings-card settings-ob-card" id="slots">
    <header class="settings-ob-card-head">
      <h5><?= e(t('settings.ob_time_slots')) ?></h5>
      <p class="muted small"><?= e(t('settings.ob_slots_form_hint')) ?></p>
    </header>
    <div class="settings-ob-slots">
    <?php foreach (array_merge($slots, [['id' => 0, 'label' => '', 'start_time' => '', 'capacity' => null, 'weekdays' => '1234567', 'is_active' => true, 'sort_order' => count($slots)]]) as $sl): ?>
    <form method="POST" action="<?= e($serviceAction) ?>#slots" class="booking-slot-row settings-ob-slot-card">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="booking_slot_save">
      <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $sl['id'] ?>">
      <div class="form-grid cols-3">
        <div class="form-group"><label><?= e(t('settings.label')) ?></label><input name="label" value="<?= e($sl['label']) ?>" maxlength="100" placeholder="<?= $sl['id'] ? '' : 'Morning (7–9 AM)' ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_starts')) ?></label><input type="time" name="start_time" value="<?= e($sl['start_time']) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
        <div class="form-group"><label><?= e(t('settings.ob_capacity')) ?></label><input type="number" name="capacity" min="0" value="<?= e((string) ($sl['capacity'] ?? '')) ?>"<?= $bookingCatalogCanEdit ? '' : ' readonly' ?>></div>
      </div>
      <div class="booking-slot-days"><?php foreach ($wd as $k => $l): ?><label class="settings-check inline"><input type="checkbox" name="weekdays[]" value="<?= $k ?>"<?= $chk(str_contains((string) $sl['weekdays'], (string) $k)) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>><?= $l ?></label><?php endforeach; ?></div>
      <div class="settings-ob-slot-actions">
        <label class="settings-check"><input type="checkbox" name="is_active" value="1"<?= $chk((bool) $sl['is_active']) ?><?= $bookingCatalogCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_slot_active')) ?></label>
        <input type="hidden" name="sort_order" value="<?= (int) $sl['sort_order'] ?>">
        <?php if ($bookingCatalogCanEdit): ?>
        <button class="btn btn-outline btn-sm" type="submit"><?= $sl['id'] ? e(t('common.update')) : e(t('settings.ob_slot_add')) ?></button>
        <?php endif; ?>
      </div>
    </form>
    <?php endforeach; ?>
    </div>
  </article>
  <?php endif; ?>
</div>
