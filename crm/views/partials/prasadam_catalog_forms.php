<?php
/** @var string $prasadamFormAction @var bool $prasadamCanEdit @var list<array<string,mixed>> $prasadamCatalogRows */
foreach ($prasadamCatalogRows as $cat):
    $id = (int) $cat['id'];
    $price = $id > 0 ? number_format(((int) $cat['price_paise']) / 100, 2, '.', '') : '';
?>
<form method="POST" action="<?= e($prasadamFormAction) ?>" class="prasadam-catalog-form settings-ob-prasadam-item">
  <?= csrf_field() ?>
  <?php if (str_contains($prasadamFormAction, 'settings')): ?>
    <input type="hidden" name="form" value="booking_prasadam">
  <?php endif; ?>
  <input type="hidden" name="catalog_id" value="<?= $id ?>">
  <?php if ($id > 0): ?>
    <p class="settings-ob-prasadam-title"><?= e((string) $cat['name']) ?></p>
  <?php else: ?>
    <p class="settings-ob-prasadam-title settings-ob-prasadam-title-new"><?= e(t('settings.ob_prasadam_add')) ?></p>
  <?php endif; ?>
  <div class="form-grid">
    <div class="form-group"><label><?= e(t('settings.ob_prasadam_name')) ?></label><input name="catalog_name" maxlength="100" value="<?= e((string) $cat['name']) ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?> required></div>
    <div class="form-group"><label><?= e(t('settings.ob_prasadam_price')) ?></label><input name="catalog_price" inputmode="decimal" value="<?= e($price) ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?> required></div>
    <div class="form-group"><label><?= e(t('settings.ob_prasadam_max')) ?></label><input type="number" name="catalog_max" min="1" max="5000" value="<?= (int) $cat['max_per_booking'] ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?>></div>
    <div class="form-group"><label><?= e(t('settings.ob_prasadam_daily')) ?></label><input type="number" name="catalog_daily_max" min="1" max="50000" value="<?= e(isset($cat['daily_max']) && $cat['daily_max'] !== null ? (string) (int) $cat['daily_max'] : '') ?>" placeholder="<?= e(t('settings.ob_prasadam_daily_ph')) ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?>></div>
    <div class="form-group full"><label><?= e(t('settings.ob_prasadam_desc')) ?></label><input name="catalog_description" maxlength="255" value="<?= e((string) ($cat['description'] ?? '')) ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?>></div>
    <div class="form-group"><label><?= e(t('settings.ob_prasadam_sort')) ?></label><input type="number" name="catalog_sort" value="<?= (int) $cat['sort_order'] ?>"<?= $prasadamCanEdit ? '' : ' readonly' ?>></div>
    <div class="form-group"><label class="settings-check"><input type="checkbox" name="catalog_active" value="1"<?= !empty($cat['is_active']) ? ' checked' : '' ?><?= $prasadamCanEdit ? '' : ' disabled' ?>> <?= e(t('settings.ob_prasadam_active')) ?></label></div>
  </div>
  <?php if ($prasadamCanEdit): ?>
  <div class="identity-actions">
    <button class="btn btn-outline" type="submit"><?= $id ? e(t('settings.ob_prasadam_save')) : e(t('settings.ob_prasadam_add')) ?></button>
  </div>
  <?php endif; ?>
</form>
<?php endforeach; ?>
