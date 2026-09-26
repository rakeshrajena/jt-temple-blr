<?php
/** @var array{name: string, phone: string, email: string, address: string, pan: string} $values */
?>
<div class="panel">
  <h3><?= e(t('ui.add_devotee')) ?></h3>
  <p class="sub">Save the name and contact details before a gift is recorded. A phone number can belong to only one devotee, so later gifts match the same person.</p>
  <form method="POST" action="<?= e(url('donors/new')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label><?= e(t('common.name')) ?></label><input type="text" name="name" maxlength="150" required value="<?= e($values['name']) ?>"></div>
      <div class="form-group"><label><?= e(t('common.phone')) ?></label><input type="text" name="phone" maxlength="20" value="<?= e($values['phone']) ?>"></div>
      <div class="form-group"><label><?= e(t('common.email')) ?></label><input type="text" name="email" maxlength="120" value="<?= e($values['email']) ?>" placeholder="name@example.com"></div>
      <div class="form-group"><label><?= e(t('common.address')) ?></label><input type="text" name="address" maxlength="500" value="<?= e($values['address']) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.pan')) ?></label><input type="text" name="pan" maxlength="10" value="<?= e($values['pan']) ?>" placeholder="ABCDE1234F"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit"><?= e(t('ui.save_devotee')) ?></button>
      <a class="btn btn-outline" href="<?= e(url('donors')) ?>"><?= e(t('ui.all_devotees')) ?></a>
    </div>
  </form>
</div>
