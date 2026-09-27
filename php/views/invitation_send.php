<?php
/** @var array<string, mixed> $invitation */
/** @var list<array{id:int,name:string,email:string}> $donors */
/** @var bool $ready */
/** @var bool $mailReady */
?>
<div class="panel">
  <h3><?= e((string) $invitation['title']) ?></h3>
  <p class="sub">These are the devotees who have an email address. Tick the people you want, or tick everyone, then send. One click sends the invitation. Devotees without an email address are not listed.</p>
  <p><a href="<?= e(url('invitations/' . $invitation['id'])) ?>">Edit the design</a></p>
  <?php if (!$mailReady): ?>
    <p>Outgoing mail is not configured. An Admin can save it under Settings.</p>
  <?php endif; ?>
  <?php if (!$ready): ?>
    <p>Add words, a picture, or a video link on the design before sending.</p>
  <?php elseif ($donors === []): ?>
    <p>No devotee has an email address yet.</p>
  <?php else: ?>
    <p><?= e((string) count($donors)) ?> devotee<?= count($donors) === 1 ? '' : 's' ?> with an email address. Up to <?= e((string) invitation_send_limit()) ?> can be sent at once.</p>
    <form method="POST" action="<?= e(url('invitations/' . $invitation['id'] . '/send')) ?>">
      <?= csrf_field() ?>
      <table class="data-table invite-people">
        <tr>
          <th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all devotees with email"></th>
          <th>Devotee</th>
          <th>Email</th>
        </tr>
        <?php foreach ($donors as $donor): ?>
          <tr>
            <td><input type="checkbox" class="invite-check" name="donor_ids[]" value="<?= e((string) $donor['id']) ?>" aria-label="Select <?= e($donor['name']) ?>"></td>
            <td><?= e($donor['name']) ?><span class="invite-mail"><?= e($donor['email']) ?></span></td>
            <td class="invite-mail-col"><?= e($donor['email']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit" id="inviteSend" disabled>Send invitation (<span id="inviteCount">0</span>)</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<script>
(function () {
  const boxes = Array.from(document.querySelectorAll('.invite-check'));
  const all = document.getElementById('selectAll');
  const send = document.getElementById('inviteSend');
  const count = document.getElementById('inviteCount');
  if (!send || boxes.length === 0) return;
  function refresh() {
    const n = boxes.filter(function (box) { return box.checked; }).length;
    send.disabled = n === 0;
    if (count) count.textContent = String(n);
    if (all) all.checked = n === boxes.length;
  }
  boxes.forEach(function (box) { box.addEventListener('change', refresh); });
  if (all) {
    all.addEventListener('change', function () {
      boxes.forEach(function (box) { box.checked = all.checked; });
      refresh();
    });
  }
  refresh();
})();
</script>
