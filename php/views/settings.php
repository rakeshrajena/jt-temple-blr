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
$bookLabels = ['cash' => 'Cash book', 'bank' => 'Bank book', 'none' => 'Not in the books'];
$directionLabels = ['in' => 'In', 'out' => 'Out'];
$storeLabels = ['inventory' => 'Inventory', 'food' => 'Food', 'both' => 'Inventory and food'];
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

  <div id="choices" class="settings-block">
    <div class="settings-head">
      <div>
        <h3>Form choices</h3>
        <p>Add a choice with the form. Update or remove it in the table. The same name cannot be added twice in one list.</p>
      </div>
    </div>
    <?php foreach ($groups as $group => $keys): ?>
      <h4 class="settings-kicker"><?= e($group) ?></h4>
      <div class="settings-choices">
        <?php foreach ($keys as $key): ?>
          <?php
            $meta = $selectionCatalog[$key];
            $rows = selection_editor_rows($key);
            $limit = selection_name_limit($key);
          ?>
          <section id="choice-<?= e($key) ?>" class="settings-choice<?= in_array($key, $wide, true) ? ' wide' : '' ?>">
            <header><h4><?= e($meta['label']) ?></h4></header>
            <p class="where"><?= e($meta['modules']) ?></p>
            <form class="choice-add" method="POST" action="<?= e(url('settings')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="selections">
              <input type="hidden" name="op" value="add">
              <input type="hidden" name="choice_key" value="<?= e($key) ?>">
              <?php if ($meta['kind'] === 'labeled'): ?>
                <div class="form-group"><label>Value</label><input type="text" name="choice_value" maxlength="30" required></div>
                <div class="form-group"><label>Label</label><input type="text" name="choice_label" maxlength="80" placeholder="Shown in the box"></div>
              <?php else: ?>
                <div class="form-group"><label>Name</label><input type="text" name="choice_name" maxlength="<?= e((string) $limit) ?>" required></div>
              <?php endif; ?>
              <?php if ($meta['kind'] === 'payment'): ?>
                <div class="form-group"><label>Books</label>
                  <select name="choice_book"><?php foreach ($bookLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
              <?php elseif ($meta['kind'] === 'movement'): ?>
                <div class="form-group"><label>Direction</label>
                  <select name="choice_direction"><?php foreach ($directionLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Store</label>
                  <select name="choice_store"><?php foreach ($storeLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <label class="settings-check"><input type="checkbox" name="choice_approval" value="1"> Wait for approval</label>
              <?php endif; ?>
              <button class="btn btn-primary" type="submit">Add</button>
            </form>
            <div class="choice-table-wrap">
              <table class="choice-table">
                <tr>
                  <?php if ($meta['kind'] === 'labeled'): ?>
                    <th>Value</th><th>Label</th>
                  <?php else: ?>
                    <th>Name</th>
                  <?php endif; ?>
                  <?php if ($meta['kind'] === 'payment'): ?><th>Books</th><?php endif; ?>
                  <?php if ($meta['kind'] === 'movement'): ?><th>Direction</th><th>Store</th><th>Approval</th><?php endif; ?>
                  <th></th>
                </tr>
                <?php foreach ($rows as $index => $row): ?>
                  <?php
                    $identity = $meta['kind'] === 'labeled' ? (string) $row['value'] : (string) $row['name'];
                    $locked = in_array($identity, $meta['required'], true);
                    $formId = 'choice-form-' . $key . '-' . $index;
                    $bookLocked = $meta['kind'] === 'payment' && in_array($identity, ['Cash', 'In-Kind'], true);
                  ?>
                  <tr>
                    <td>
                      <form id="<?= e($formId) ?>" method="POST" action="<?= e(url('settings')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="selections">
                        <input type="hidden" name="choice_key" value="<?= e($key) ?>">
                        <input type="hidden" name="choice_index" value="<?= e((string) $index) ?>">
                      </form>
                      <?php if ($meta['kind'] === 'labeled'): ?>
                        <input form="<?= e($formId) ?>" type="text" name="choice_value" maxlength="30" value="<?= e((string) $row['value']) ?>"<?= $locked ? ' readonly' : '' ?> required>
                      <?php else: ?>
                        <input form="<?= e($formId) ?>" type="text" name="choice_name" maxlength="<?= e((string) $limit) ?>" value="<?= e((string) $row['name']) ?>"<?= $locked ? ' readonly' : '' ?> required>
                      <?php endif; ?>
                    </td>
                    <?php if ($meta['kind'] === 'labeled'): ?>
                      <td><input form="<?= e($formId) ?>" type="text" name="choice_label" maxlength="80" value="<?= e((string) $row['label']) ?>" required></td>
                    <?php endif; ?>
                    <?php if ($meta['kind'] === 'payment'): ?>
                      <td>
                        <?php if ($bookLocked): ?>
                          <input form="<?= e($formId) ?>" type="hidden" name="choice_book" value="<?= e((string) $row['book']) ?>">
                          <span class="choice-fixed"><?= e($bookLabels[$row['book']] ?? (string) $row['book']) ?></span>
                        <?php else: ?>
                          <select form="<?= e($formId) ?>" name="choice_book">
                            <?php foreach ($bookLabels as $value => $label): ?>
                              <option value="<?= e($value) ?>"<?= $row['book'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <?php if ($meta['kind'] === 'movement'): ?>
                      <td>
                        <select form="<?= e($formId) ?>" name="choice_direction">
                          <?php foreach ($directionLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $row['direction'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <select form="<?= e($formId) ?>" name="choice_store">
                          <?php foreach ($storeLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $row['store'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td><label class="settings-check"><input form="<?= e($formId) ?>" type="checkbox" name="choice_approval" value="1"<?= !empty($row['approval']) ? ' checked' : '' ?>> Wait</label></td>
                    <?php endif; ?>
                    <td class="choice-actions">
                      <button form="<?= e($formId) ?>" class="btn btn-outline btn-sm" type="submit" name="op" value="update">Update</button>
                      <?php if (!$locked): ?>
                        <button form="<?= e($formId) ?>" class="btn btn-danger btn-sm" type="submit" name="op" value="delete" onclick="return confirm('Remove this choice?')">Remove</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <p class="settings-note">A name already in a list cannot be added again. Names the forms rely on stay in the table and cannot be removed. A pledge is a record on the devotee page. Approval status, subscriber status, invoice status, bank match, user role, and cash-book deposit or withdraw stay fixed.</p>
  </div>
</div>
