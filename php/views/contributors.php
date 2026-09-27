<?php
/** @var list<array<string, mixed>> $contributors */
/** @var bool $isAdmin */
?>
<?php if ($isAdmin): ?>
<div class="reveal-group">
  <div class="action-bar">
    <button class="btn btn-outline" type="button" data-reveal="reveal-contributor"><?= e(t('contributors.add')) ?></button>
  </div>
  <div class="panel reveal-panel" id="reveal-contributor" hidden>
    <h3><?= e(t('contributors.add')) ?><?= help_tip(t('contributors.add_note')) ?></h3>
    <form method="POST" action="<?= e(url('contributors')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php require __DIR__ . '/contributor_fields.php'; ?>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('contributors.save')) ?></button></div>
    </form>
  </div>
</div>
<?php else: ?>
<p class="sub"><?= e(t('contributors.view_only')) ?></p>
<?php endif; ?>

<?php if ($contributors === []): ?>
<div class="panel"><div class="empty-state"><?= e(t('contributors.empty')) ?></div></div>
<?php else: ?>
<div class="contributor-grid">
  <?php foreach ($contributors as $person): ?>
  <?php
    $name = (string) $person['name'];
    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
    $photo = (string) ($person['image_file'] ?? '');
  ?>
  <?php
    $location = (string) $person['location'];
    $contact = (string) $person['contact'];
    $email = (string) $person['email'];
    $profile = (string) $person['profile_url'];
    $profileShown = $profile === '' ? '' : (preg_replace('#^https?://#i', '', rtrim($profile, '/')) ?? $profile);
  ?>
  <article class="contributor-card">
    <header class="contributor-head">
      <?php if ($photo !== ''): ?>
        <img class="contributor-photo" src="<?= e(url('contributors/' . $person['id'] . '/photo')) ?>" alt="">
      <?php else: ?>
        <div class="contributor-photo contributor-initial" aria-hidden="true"><?= e($initial) ?></div>
      <?php endif; ?>
      <div class="contributor-id">
        <h3><?= e($name) ?></h3>
        <?php if ((string) $person['designation'] !== ''): ?>
          <p class="contributor-role"><?= e((string) $person['designation']) ?></p>
        <?php endif; ?>
      </div>
    </header>
    <?php if ($location !== '' || $contact !== '' || $email !== '' || $profile !== ''): ?>
    <dl class="contributor-facts">
      <?php if ($location !== ''): ?>
        <div><dt><?= e(t('contributors.location')) ?></dt><dd><?= e($location) ?></dd></div>
      <?php endif; ?>
      <?php if ($contact !== ''): ?>
        <div><dt><?= e(t('contributors.contact')) ?></dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', $contact) ?? '') ?>"><?= e($contact) ?></a></dd></div>
      <?php endif; ?>
      <?php if ($email !== ''): ?>
        <div><dt><?= e(t('common.email')) ?></dt><dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div>
      <?php endif; ?>
      <?php if ($profile !== ''): ?>
        <div><dt><?= e(t('contributors.profile')) ?></dt><dd><a href="<?= e($profile) ?>" target="_blank" rel="noopener noreferrer"><?= e($profileShown) ?></a></dd></div>
      <?php endif; ?>
    </dl>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <footer class="contributor-admin">
      <div class="reveal-group">
        <div class="contributor-actions">
          <button class="btn btn-sm btn-outline" type="button" data-reveal="contributor-edit-<?= e((string) $person['id']) ?>"><?= e(t('common.update')) ?></button>
          <form class="contributor-remove" method="POST" action="<?= e(url('contributors/' . $person['id'] . '/remove')) ?>" onsubmit="return confirm(<?= e(json_encode(t('contributors.remove_confirm'), JSON_UNESCAPED_UNICODE)) ?>);">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.remove')) ?></button>
          </form>
        </div>
        <form class="cell-form contributor-edit reveal-panel" id="contributor-edit-<?= e((string) $person['id']) ?>" hidden method="POST" action="<?= e(url('contributors/' . $person['id'])) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <?php $contributor = $person; require __DIR__ . '/contributor_fields.php'; ?>
          <button class="btn btn-sm btn-primary" type="submit"><?= e(t('contributors.save')) ?></button>
        </form>
      </div>
    </footer>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
