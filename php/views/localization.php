<?php
/** @var list<array{code: string, name: string, native: string}> $languages */
/** @var string $selected */
/** @var array<string, string> $english */
/** @var array<string, string> $phrases */

$groups = [];
foreach ($english as $key => $unused) {
    $prefix = strstr($key, '.', true);
    $groups[$prefix !== false ? $prefix : $key][] = $key;
}
?>
<div class="locale-page">
  <p class="settings-note"><?= e(t('locale.static_note')) ?></p>
  <p class="settings-note"><?= e(t('locale.intro')) ?></p>

  <section class="panel">
    <h3><?= e(t('locale.title')) ?></h3>
    <table class="data-table">
      <tr>
        <th><?= e(t('locale.code')) ?></th>
        <th><?= e(t('locale.english_name')) ?></th>
        <th><?= e(t('locale.native_name')) ?></th>
        <th><?= e(t('locale.file')) ?></th>
        <th></th>
      </tr>
      <?php foreach ($languages as $language): ?>
        <tr>
          <td><?= e($language['code']) ?></td>
          <td><?= e($language['name']) ?></td>
          <td><?= e($language['native']) ?></td>
          <td><code><?= e($language['code']) ?>.json</code></td>
          <td>
            <a class="btn btn-sm btn-outline" href="<?= e(url('localization', ['code' => $language['code']])) ?>"><?= e(t('locale.phrases')) ?></a>
            <?php if ($language['code'] !== 'en'): ?>
              <form method="POST" action="<?= e(url('localization')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="code" value="<?= e($language['code']) ?>">
                <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm(<?= e(json_encode(t('locale.remove') . '?', JSON_UNESCAPED_UNICODE)) ?>)"><?= e(t('common.remove')) ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <form method="POST" action="<?= e(url('localization')) ?>" class="locale-add">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="add">
      <div class="form-group">
        <label for="new-code"><?= e(t('locale.code')) ?></label>
        <input id="new-code" name="code" maxlength="8" pattern="[A-Za-z]{2,8}" required placeholder="ta">
      </div>
      <div class="form-group">
        <label for="new-name"><?= e(t('locale.english_name')) ?></label>
        <input id="new-name" name="name" maxlength="40" required>
      </div>
      <div class="form-group">
        <label for="new-native"><?= e(t('locale.native_name')) ?></label>
        <input id="new-native" name="native" maxlength="40" required>
      </div>
      <button class="btn btn-primary" type="submit"><?= e(t('locale.add_button')) ?></button>
    </form>
  </section>

  <section class="panel">
    <h3><?= e(t('locale.phrases')) ?> — <?= e($selected) ?>.json<?= $selected !== 'en' ? help_tip(t('locale.blank_hint')) : '' ?></h3>
    <div class="form-group">
      <label for="phrase-search"><?= e(t('locale.search')) ?></label>
      <input id="phrase-search" type="search" autocomplete="off">
    </div>
    <form method="POST" action="<?= e(url('localization')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="code" value="<?= e($selected) ?>">
      <?php foreach ($groups as $prefix => $keys): ?>
        <h4 class="locale-group"><?= e($prefix) ?></h4>
        <table class="data-table locale-phrases">
          <tr>
            <th><?= e(t('locale.english')) ?></th>
            <th><?= e(t('locale.translation')) ?></th>
          </tr>
          <?php foreach ($keys as $key): ?>
            <tr data-phrase="<?= e(mb_strtolower($key . ' ' . $english[$key])) ?>">
              <td>
                <div><?= e($english[$key]) ?></div>
                <div class="locale-key"><?= e($key) ?></div>
              </td>
              <td>
                <input type="hidden" name="phrase_key[]" value="<?= e($key) ?>">
                <input type="text" name="phrase_value[]" value="<?= e($phrases[$key] ?? '') ?>" maxlength="500"<?= $selected === 'en' ? ' required' : '' ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endforeach; ?>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= e(t('locale.save')) ?></button>
      </div>
    </form>
  </section>
</div>
<script>
  document.getElementById('phrase-search').addEventListener('input', function () {
    var query = this.value.trim().toLowerCase();
    document.querySelectorAll('.locale-phrases tr[data-phrase]').forEach(function (row) {
      row.hidden = query !== '' && row.getAttribute('data-phrase').indexOf(query) === -1;
    });
  });
</script>
