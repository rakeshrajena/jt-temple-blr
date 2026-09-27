<?php
/** @var list<array<string, mixed>> $invitations */
?>
<div class="panel">
  <h3>Invitations</h3>
  <p class="sub">Design a card with words, a picture, or a video link. Then send it in one click to devotees who have an email address.</p>
  <div class="reveal-group">
  <div class="action-bar">
    <button class="btn btn-outline" type="button" data-reveal="reveal-invite">Design invitation</button>
  </div>
  <div class="reveal-panel" id="reveal-invite" hidden>
  <form method="POST" action="<?= e(url('invitations')) ?>" class="invite-create">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="title">Invitation name</label>
      <input id="title" name="title" maxlength="120" required placeholder="Rath Yatra">
    </div>
    <button class="btn btn-gold btn-sm" type="submit">Design invitation</button>
  </form>
  </div>
  </div>
  <?php if ($invitations === []): ?>
    <p>No invitations yet.</p>
  <?php else: ?>
    <table class="data-table">
      <tr><th>Name</th><th>Subject</th><th>Updated</th><th></th></tr>
      <?php foreach ($invitations as $invitation): ?>
        <tr>
          <td><a href="<?= e(url('invitations/' . $invitation['id'])) ?>"><?= e((string) $invitation['title']) ?></a></td>
          <td><?= e((string) $invitation['subject']) ?></td>
          <td><?= e((string) $invitation['updated_at']) ?></td>
          <td>
            <a class="btn btn-outline btn-sm" href="<?= e(url('invitations/' . $invitation['id'] . '/send')) ?>">Send</a>
            <a class="btn btn-outline btn-sm" href="<?= e(url('invitations/' . $invitation['id'] . '/send')) ?>#sent-box">Sent box (<?= e((string) (int) $invitation['sent_count']) ?>)</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
