<?php
/** @var array<string, string> $settings */
?>
<div class="panel">
  <h3>Outgoing email</h3>
  <p class="sub">These details are used when an invoice is sent to a devotee who has an email address. Until a mail server and a From address are saved, the message is only written to the notification log. The password is stored for this app and is not shown again. Leave it blank to keep the saved password.</p>
  <form method="POST" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>SMTP host</label><input type="text" name="smtp_host" value="<?= e($settings['smtp_host']) ?>" placeholder="smtp.example.com" maxlength="200"></div>
      <div class="form-group"><label>Port</label><input type="number" name="smtp_port" min="1" max="65535" value="<?= e($settings['smtp_port']) ?>" required></div>
      <div class="form-group">
        <label>Encryption</label>
        <select name="smtp_encryption">
          <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $settings['smtp_encryption'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Username</label><input type="text" name="smtp_username" value="<?= e($settings['smtp_username']) ?>" maxlength="200" autocomplete="off"></div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="smtp_password" value="" maxlength="200" autocomplete="new-password" placeholder="<?= $settings['password_saved'] === '1' ? 'Saved' : 'Not set' ?>">
      </div>
      <div class="form-group"><label>From email</label><input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email']) ?>" maxlength="200"></div>
      <div class="form-group"><label>From name</label><input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name']) ?>" maxlength="120"></div>
      <?php if ($settings['password_saved'] === '1'): ?>
      <div class="form-group"><label>Saved password</label><label><input type="checkbox" name="clear_smtp_password" value="1"> Remove it</label></div>
      <?php endif; ?>
    </div>
    <h3>WhatsApp Web</h3>
    <p class="sub">This does not call a WhatsApp API. Subscriptions opens WhatsApp Web with the devotee’s number and this message, and someone presses send there. Use {name}, {period}, {amount}, {link}, and {invoice} in the message.</p>
    <div class="form-grid">
      <div class="form-group"><label>Country code</label><input type="text" name="whatsapp_country_code" value="<?= e($settings['whatsapp_country_code']) ?>" maxlength="4" required></div>
      <div class="form-group full"><label>Message</label><textarea name="whatsapp_template" rows="4" maxlength="1000" required><?= e($settings['whatsapp_template']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save settings</button></div>
  </form>
</div>
