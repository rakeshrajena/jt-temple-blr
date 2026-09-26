<?php
/** @var array{name: string, phone: string, email: string, address: string, pan: string} $values */
?>
<div class="panel">
  <h3>Add a devotee</h3>
  <p class="sub">Save the name and contact details before a gift is recorded. A phone number can belong to only one devotee, so later gifts match the same person.</p>
  <form method="POST" action="<?= e(url('donors/new')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Name</label><input type="text" name="name" maxlength="150" required value="<?= e($values['name']) ?>"></div>
      <div class="form-group"><label>Phone</label><input type="text" name="phone" maxlength="20" value="<?= e($values['phone']) ?>"></div>
      <div class="form-group"><label>Email</label><input type="text" name="email" maxlength="120" value="<?= e($values['email']) ?>" placeholder="name@example.com"></div>
      <div class="form-group"><label>Address</label><input type="text" name="address" maxlength="500" value="<?= e($values['address']) ?>"></div>
      <div class="form-group"><label>PAN</label><input type="text" name="pan" maxlength="10" value="<?= e($values['pan']) ?>" placeholder="ABCDE1234F"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit">Save devotee</button>
      <a class="btn btn-outline" href="<?= e(url('donors')) ?>">All devotees</a>
    </div>
  </form>
</div>
