<?php
/** @var array<string, string> $settings */
/** @var array<string, array{label: string, modules: string, kind: string, required: list<string>}> $selectionCatalog */

$mailReady = trim($settings['smtp_host']) !== ''
    && filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL) !== false;
$passwordSaved = $settings['password_saved'] === '1';
$groups = [
    t('settings.shared') => ['payment_modes', 'units', 'sources', 'deities'],
    t('settings.single') => [
        'inventory_categories',
        'expense_categories',
        'conditions',
        'movements',
        'vastra_statuses',
        'donation_types',
        'purposes',
        'puja_purposes',
        'plans',
        'billing_cycles',
        'subscriber_statuses',
    ],
];
$wide = ['payment_modes', 'movements', 'donation_types', 'puja_purposes'];
$bookLabels = ['cash' => t('settings.book_cash'), 'bank' => t('settings.book_bank'), 'none' => t('settings.book_none')];
$directionLabels = ['in' => t('settings.dir_in'), 'out' => t('settings.dir_out')];
$storeLabels = ['inventory' => t('settings.store_inventory'), 'food' => t('settings.store_food'), 'both' => t('settings.store_both')];
?>
<div class="settings-page">
  <nav class="settings-nav" aria-label="<?= e(t('settings.nav')) ?>">
    <a href="#identity"><?= e(t('settings.identity')) ?></a>
    <a href="#approval"><?= e(t('settings.approval')) ?></a>
    <a href="#messages"><?= e(t('settings.messages')) ?></a>
    <a href="#choices"><?= e(t('settings.choices')) ?></a>
  </nav>

  <form id="identity" class="settings-block" method="POST" action="<?= e(url('settings')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="brand">
    <section class="settings-card identity-card">
      <h3><?= e(t('settings.identity')) ?><?= help_tip(t('settings.identity_intro')) ?></h3>
      <div class="identity-lockup">
        <img src="<?= e(app_logo_url()) ?>" alt="" class="identity-logo">
        <div>
          <p class="identity-name"><?= e(app_display_name()) ?></p>
          <p class="identity-place"><?= e(app_place()) ?></p>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group full">
          <label for="app_name"><?= e(t('settings.temple_name')) ?></label>
          <input id="app_name" type="text" name="app_name" value="<?= e(app_display_name()) ?>" maxlength="80" required>
        </div>
        <div class="form-group full">
          <label for="app_place"><?= e(t('settings.place')) ?></label>
          <input id="app_place" type="text" name="app_place" value="<?= e(app_place()) ?>" maxlength="80" required>
        </div>
        <div class="form-group">
          <label for="watermark_receipt"><?= e(t('settings.watermark_receipt')) ?><?= help_tip(t('settings.watermark_hint')) ?></label>
          <input id="watermark_receipt" type="number" name="watermark_receipt" min="0" max="100" step="1" value="<?= e((string) brand_watermark_level('receipt')) ?>" required>
        </div>
        <div class="form-group">
          <label for="watermark_coupon"><?= e(t('settings.watermark_coupon')) ?><?= help_tip(t('settings.watermark_hint')) ?></label>
          <input id="watermark_coupon" type="number" name="watermark_coupon" min="0" max="100" step="1" value="<?= e((string) brand_watermark_level('coupon')) ?>" required>
        </div>
        <div class="form-group full">
          <label for="logo"><?= e(t('settings.logo')) ?><?= help_tip(t('settings.logo_hint')) ?></label>
          <input id="logo" type="file" name="logo" accept="image/*,.heic,.heif,.avif,.jxl,.bmp,.tif,.tiff,.ico,.svg,.webp,.gif,.jpg,.jpeg,.png,.jfif,.ppm,.wbmp">
        </div>
      </div>
      <?php if (brand_has_custom_logo()): ?>
        <label class="settings-check"><input type="checkbox" name="use_default_logo" value="1"> <?= e(t('settings.logo_default')) ?></label>
      <?php endif; ?>
      <div class="identity-actions">
        <p><?= e(t('settings.identity_scope')) ?></p>
        <button class="btn btn-primary" type="submit"><?= e(t('settings.save_identity')) ?></button>
      </div>
    </section>
  </form>

  <form id="approval" class="settings-block" method="POST" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="approval">
    <section class="settings-card">
      <h3><?= e(t('settings.approval')) ?><?= help_tip(t('settings.approval_intro')) ?></h3>
      <div class="form-grid">
        <div class="form-group">
          <label for="treasurer_limit"><?= e(t('settings.treasurer_limit')) ?></label>
          <input id="treasurer_limit" type="number" name="treasurer_limit" min="0" max="100000000" step="0.01" required value="<?= e(number_format((float) approval_limit('Treasurer'), 2, '.', '')) ?>">
        </div>
        <div class="form-group">
          <label for="staff_limit"><?= e(t('settings.staff_limit')) ?></label>
          <input id="staff_limit" type="number" name="staff_limit" min="0" max="100000000" step="0.01" required value="<?= e(number_format((float) approval_limit('Staff'), 2, '.', '')) ?>">
        </div>
      </div>
      <div class="identity-actions">
        <p><?= e(t('settings.approval_scope')) ?></p>
        <button class="btn btn-primary" type="submit"><?= e(t('settings.save_approval')) ?></button>
      </div>
    </section>
  </form>

  <form id="messages" class="settings-block" method="POST" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="messages">
    <div class="settings-head">
      <div>
        <h3><?= e(t('settings.messages')) ?><?= help_tip(t('settings.messages_intro')) ?></h3>
      </div>
      <span class="badge <?= $mailReady ? 'badge-green' : 'badge-amber' ?>"><?= e($mailReady ? t('settings.mail_ready') : t('settings.mail_missing')) ?></span>
    </div>
    <div class="settings-grid">
      <section class="settings-card">
        <h4><?= e(t('settings.outgoing')) ?><?= help_tip(t('settings.outgoing_hint')) ?></h4>
        <div class="form-grid">
          <div class="form-group"><label><?= e(t('settings.host')) ?></label><input type="text" name="smtp_host" value="<?= e($settings['smtp_host']) ?>" placeholder="smtp.example.com" maxlength="200"></div>
          <div class="form-group"><label><?= e(t('settings.port')) ?></label><input type="number" name="smtp_port" min="1" max="65535" value="<?= e($settings['smtp_port']) ?>" required></div>
          <div class="form-group">
            <label><?= e(t('settings.encryption')) ?></label>
            <select name="smtp_encryption">
              <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => t('settings.none')] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $settings['smtp_encryption'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label><?= e(t('settings.username')) ?></label><input type="text" name="smtp_username" value="<?= e($settings['smtp_username']) ?>" maxlength="200" autocomplete="off"></div>
          <div class="form-group">
            <label><?= e(t('settings.password')) ?></label>
            <input type="password" name="smtp_password" value="" maxlength="200" autocomplete="new-password" placeholder="<?= e($passwordSaved ? t('settings.keep_password') : t('settings.not_set')) ?>">
          </div>
          <div class="form-group"><label><?= e(t('settings.from_email')) ?></label><input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email']) ?>" maxlength="200"></div>
          <div class="form-group full"><label><?= e(t('settings.from_name')) ?></label><input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name']) ?>" maxlength="120"></div>
        </div>
        <?php if ($passwordSaved): ?>
          <label class="settings-check"><input type="checkbox" name="clear_smtp_password" value="1"> <?= e(t('settings.remove_password')) ?></label>
        <?php endif; ?>
      </section>
      <section class="settings-card">
        <h4><?= e(t('settings.whatsapp')) ?><?= help_tip(t('settings.whatsapp_hint')) ?></h4>
        <div class="settings-tokens" aria-label="Message placeholders">
          <?php foreach (['{name}', '{period}', '{amount}', '{link}', '{invoice}'] as $token): ?>
            <code><?= e($token) ?></code>
          <?php endforeach; ?>
        </div>
        <div class="form-group"><label><?= e(t('settings.country')) ?></label><input type="text" name="whatsapp_country_code" value="<?= e($settings['whatsapp_country_code']) ?>" maxlength="4" required></div>
        <div class="form-group" style="margin-top:14px;"><label><?= e(t('settings.message')) ?></label><textarea name="whatsapp_template" rows="8" maxlength="1000" required><?= e($settings['whatsapp_template']) ?></textarea></div>
      </section>
    </div>
    <div class="settings-save is-static">
      <p><?= e(t('settings.save_scope')) ?></p>
      <button class="btn btn-primary" type="submit"><?= e(t('settings.save')) ?></button>
    </div>
  </form>

  <div id="choices" class="settings-block">
    <div class="settings-head">
      <div>
        <h3><?= e(t('settings.choices')) ?><?= help_tip(t('settings.choices_intro') . ' ' . t('settings.note')) ?></h3>
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
            <header><h4><?= e(t('settings.choice.' . $key)) ?><?= help_tip(t('settings.where.' . $key)) ?></h4></header>
            <form class="choice-add" method="POST" action="<?= e(url('settings')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="selections">
              <input type="hidden" name="op" value="add">
              <input type="hidden" name="choice_key" value="<?= e($key) ?>">
              <?php if ($meta['kind'] === 'labeled'): ?>
                <div class="form-group"><label><?= e(t('settings.value')) ?></label><input type="text" name="choice_value" maxlength="30" required></div>
                <div class="form-group"><label><?= e(t('settings.label')) ?></label><input type="text" name="choice_label" maxlength="80" placeholder="<?= e(t('settings.shown')) ?>"></div>
              <?php else: ?>
                <div class="form-group"><label><?= e(t('common.name')) ?></label><input type="text" name="choice_name" maxlength="<?= e((string) $limit) ?>" required></div>
              <?php endif; ?>
              <?php if ($meta['kind'] === 'payment'): ?>
                <div class="form-group"><label><?= e(t('settings.books')) ?></label>
                  <select name="choice_book"><?php foreach ($bookLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
              <?php elseif ($meta['kind'] === 'movement'): ?>
                <div class="form-group"><label><?= e(t('settings.direction')) ?></label>
                  <select name="choice_direction"><?php foreach ($directionLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label><?= e(t('settings.store')) ?></label>
                  <select name="choice_store"><?php foreach ($storeLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <label class="settings-check"><input type="checkbox" name="choice_approval" value="1"> <?= e(t('settings.approval_wait')) ?></label>
              <?php elseif ($meta['kind'] === 'priced'): ?>
                <div class="form-group"><label><?= e(t('common.amount')) ?></label><input type="number" name="choice_amount" min="0.01" step="0.01" required></div>
              <?php endif; ?>
              <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
            </form>
            <div class="choice-table-wrap">
              <table class="choice-table">
                <tr>
                  <?php if ($meta['kind'] === 'labeled'): ?>
                    <th><?= e(t('settings.value')) ?></th><th><?= e(t('settings.label')) ?></th>
                  <?php else: ?>
                    <th><?= e(t('common.name')) ?></th>
                  <?php endif; ?>
                  <?php if ($meta['kind'] === 'priced'): ?><th><?= e(t('common.amount')) ?></th><?php endif; ?>
                  <?php if ($meta['kind'] === 'payment'): ?><th><?= e(t('settings.books')) ?></th><?php endif; ?>
                  <?php if ($meta['kind'] === 'movement'): ?><th><?= e(t('settings.direction')) ?></th><th><?= e(t('settings.store')) ?></th><th><?= e(t('common.approval')) ?></th><?php endif; ?>
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
                    <?php if ($meta['kind'] === 'priced'): ?>
                      <td><input form="<?= e($formId) ?>" type="number" name="choice_amount" min="0.01" step="0.01" value="<?= e((string) $row['amount']) ?>" required></td>
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
                      <td><label class="settings-check"><input form="<?= e($formId) ?>" type="checkbox" name="choice_approval" value="1"<?= !empty($row['approval']) ? ' checked' : '' ?>> <?= e(t('settings.wait')) ?></label></td>
                    <?php endif; ?>
                    <td class="choice-actions">
                      <button form="<?= e($formId) ?>" class="btn btn-outline btn-sm" type="submit" name="op" value="update"><?= e(t('common.update')) ?></button>
                      <?php if (!$locked): ?>
                        <button form="<?= e($formId) ?>" class="btn btn-danger btn-sm" type="submit" name="op" value="delete" onclick="return confirm(<?= e(json_encode(t('settings.remove_confirm'), JSON_UNESCAPED_UNICODE)) ?>)"><?= e(t('common.remove')) ?></button>
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
  </div>
</div>
