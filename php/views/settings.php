<?php
/** @var array<string, string> $settings */
/** @var array<string, array{label: string, modules: string, kind: string, required: list<string>}> $selectionCatalog */

$mailReady = trim($settings['smtp_host']) !== ''
    && filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL) !== false;
$passwordSaved = $settings['password_saved'] === '1';
$groups = [
    'Shared lists' => ['payment_modes', 'units', 'sources', 'deities'],
    'Lists for one screen' => [
        'inventory_categories',
        'expense_categories',
        'conditions',
        'movements',
        'vastra_statuses',
        'donation_types',
        'purposes',
        'plans',
        'billing_cycles',
    ],
];
$wide = ['payment_modes', 'movements', 'donation_types'];
$formatHint = [
    'payment' => 'One line each: Name | cash, Name | bank, or Name | none.',
    'movement' => 'One line each: Name | in or out | inventory, food, or both. Add | approval when a large quantity must wait.',
    'labeled' => 'One line each. Use Value | Label when the box should show a longer name.',
    'lines' => 'One name per line.',
];
?>
<div class="settings-page">
  <nav class="settings-nav" aria-label="Settings sections">
    <a href="#messages">Messages</a>
    <a href="#choices">Form choices</a>
  </nav>

  <form id="messages" class="settings-block" method="POST" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="messages">
    <div class="settings-head">
      <div>
        <h3>Messages</h3>
        <p>Outgoing mail for invoices and receipt emails, and the note that opens in WhatsApp Web.</p>
      </div>
      <span class="badge <?= $mailReady ? 'badge-green' : 'badge-amber' ?>"><?= $mailReady ? 'Mail server ready' : 'Mail server not set' ?></span>
    </div>
    <div class="settings-grid">
      <section class="settings-card">
        <h4>Outgoing email</h4>
        <p class="hint">Until a mail server and a From address are saved, messages are written to the notification log only. The password stays hidden. Leave it blank to keep the saved one.</p>
        <div class="form-grid">
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
            <input type="password" name="smtp_password" value="" maxlength="200" autocomplete="new-password" placeholder="<?= $passwordSaved ? 'Leave blank to keep it' : 'Not set' ?>">
          </div>
          <div class="form-group"><label>From email</label><input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email']) ?>" maxlength="200"></div>
          <div class="form-group full"><label>From name</label><input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name']) ?>" maxlength="120"></div>
        </div>
        <?php if ($passwordSaved): ?>
          <label class="settings-check"><input type="checkbox" name="clear_smtp_password" value="1"> Remove the saved password</label>
        <?php endif; ?>
      </section>
      <section class="settings-card">
        <h4>WhatsApp Web</h4>
        <p class="hint">This opens WhatsApp Web with the devotee’s number and this message. Nothing is sent through a WhatsApp API.</p>
        <div class="settings-tokens" aria-label="Message placeholders">
          <?php foreach (['{name}', '{period}', '{amount}', '{link}', '{invoice}'] as $token): ?>
            <code><?= e($token) ?></code>
          <?php endforeach; ?>
        </div>
        <div class="form-group"><label>Country code</label><input type="text" name="whatsapp_country_code" value="<?= e($settings['whatsapp_country_code']) ?>" maxlength="4" required></div>
        <div class="form-group" style="margin-top:14px;"><label>Message</label><textarea name="whatsapp_template" rows="8" maxlength="1000" required><?= e($settings['whatsapp_template']) ?></textarea></div>
      </section>
    </div>
    <div class="settings-save is-static">
      <p>Saving here updates mail and WhatsApp only.</p>
      <button class="btn btn-primary" type="submit">Save messages</button>
    </div>
  </form>

  <form id="choices" class="settings-block" method="POST" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="selections">
    <div class="settings-head">
      <div>
        <h3>Form choices</h3>
        <p>A choice used for the same purpose is one list. The line under each title says which screen uses it.</p>
      </div>
    </div>
    <?php foreach ($groups as $group => $keys): ?>
      <h4 class="settings-kicker"><?= e($group) ?></h4>
      <div class="settings-choices">
        <?php foreach ($keys as $key): ?>
          <?php
            $meta = $selectionCatalog[$key];
            $text = selection_text($key);
            $rows = max(4, min(8, substr_count($text, "\n") + 2));
          ?>
          <section class="settings-choice<?= in_array($key, $wide, true) ? ' wide' : '' ?>">
            <header>
              <h4><?= e($meta['label']) ?></h4>
            </header>
            <p class="where"><?= e($meta['modules']) ?></p>
            <p class="format"><?= e($formatHint[$meta['kind']] ?? $formatHint['lines']) ?></p>
            <?php if ($meta['required'] !== []): ?>
              <div class="settings-keep">
                <span class="label">Keep</span>
                <?php foreach ($meta['required'] as $name): ?><span class="badge badge-grey"><?= e($name) ?></span><?php endforeach; ?>
              </div>
            <?php endif; ?>
            <textarea name="<?= e($key) ?>" rows="<?= e((string) $rows) ?>" maxlength="4000"><?= e($text) ?></textarea>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <p class="settings-note">A pledge is a record on the devotee page, and its purpose is typed there. Approval status, subscriber status, invoice status, bank match, user role, and cash-book deposit or withdraw stay fixed because the books match those words.</p>
    <div class="settings-save">
      <p>Names marked Keep cannot be removed. The forms rely on them.</p>
      <button class="btn btn-primary" type="submit">Save form choices</button>
    </div>
  </form>
</div>
