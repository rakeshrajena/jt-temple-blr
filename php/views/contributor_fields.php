<?php
/** @var array<string, mixed>|null $contributor */
$contributor = $contributor ?? null;
$value = static function (string $key) use ($contributor): string {
    return $contributor === null ? '' : (string) ($contributor[$key] ?? '');
};
?>
<div class="form-grid cols-3">
  <div class="form-group">
    <label><?= e(t('common.name')) ?></label>
    <input type="text" name="name" maxlength="120" required value="<?= e($value('name')) ?>">
  </div>
  <div class="form-group">
    <label><?= e(t('contributors.designation')) ?></label>
    <input type="text" name="designation" maxlength="80" value="<?= e($value('designation')) ?>">
  </div>
  <div class="form-group">
    <label><?= e(t('contributors.contact')) ?></label>
    <input type="text" name="contact" maxlength="30" value="<?= e($value('contact')) ?>" placeholder="+91 …">
  </div>
  <div class="form-group">
    <label><?= e(t('common.email')) ?></label>
    <input type="email" name="email" maxlength="120" value="<?= e($value('email')) ?>">
  </div>
  <div class="form-group">
    <label><?= e(t('contributors.location')) ?></label>
    <input type="text" name="location" maxlength="120" value="<?= e($value('location')) ?>">
  </div>
  <div class="form-group">
    <label><?= e(t('contributors.profile_url')) ?></label>
    <input type="url" name="profile_url" maxlength="300" value="<?= e($value('profile_url')) ?>" placeholder="https://">
  </div>
  <div class="form-group">
    <label><?= e(t('contributors.photo')) ?></label>
    <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
  </div>
</div>
