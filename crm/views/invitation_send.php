<?php
/** @var array<string, mixed> $invitation */
/** @var list<array{id:int,name:string,email:string}> $donors */
/** @var list<array<string, mixed>> $sent */
/** @var list<int> $sentDonorIds */
/** @var bool $ready */
/** @var bool $mailReady */
$sentDonorIds = array_fill_keys($sentDonorIds, true);
?>
<div class="panel">
  <h3><?= e((string) $invitation['title']) ?><?= help_tip('These are the devotees who have an email address. Tick the people you want, or tick everyone, then send. One click sends the invitation. Devotees without an email address are not listed.') ?></h3>
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
    <form id="inviteSendForm" method="POST" action="<?= e(url('invitations/' . $invitation['id'] . '/send')) ?>">
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
            <td><?= e($donor['name']) ?><?php if (isset($sentDonorIds[(int) $donor['id']])): ?> <span class="badge badge-green">Already sent</span><?php endif; ?><span class="invite-mail"><?= e($donor['email']) ?></span></td>
            <td class="invite-mail-col"><?= e($donor['email']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit" id="inviteSend" disabled>Send invitation (<span id="inviteCount">0</span>)</button>
      </div>
    </form>
  <?php endif; ?>
  <h3 id="sent-box">Sent box<?= help_tip('People who have already been sent this invitation.') ?></h3>
  <?php if ($sent === []): ?>
    <p>No one has been sent this invitation yet.</p>
  <?php else: ?>
    <table class="data-table">
      <tr><th>Devotee</th><th>Email</th><th>Sent</th></tr>
      <?php foreach ($sent as $row): ?>
        <?php $stamp = strtotime((string) $row['sent_at']); ?>
        <tr>
          <td><?= e((string) $row['donor_name']) ?></td>
          <td><?= e((string) $row['email']) ?></td>
          <td><?= e($stamp === false ? (string) $row['sent_at'] : date('j M Y, g:i a', $stamp)) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<script>
(function () {
  const boxes = Array.from(document.querySelectorAll('.invite-check'));
  const all = document.getElementById('selectAll');
  const send = document.getElementById('inviteSend');
  const count = document.getElementById('inviteCount');
  const form = document.getElementById('inviteSendForm');
  let sending = false;
  if (form) {
    form.addEventListener('submit', function (event) {
      if (sending) {
        event.preventDefault();
        return;
      }
      sending = true;
      if (send) send.disabled = true;
    });
  }
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
