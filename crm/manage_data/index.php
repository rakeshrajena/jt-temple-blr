<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/dbutil.php';
require __DIR__ . '/lib/purge.php';
require __DIR__ . '/lib/receipts.php';

$admin = manage_data_admin();
$keyConfigured = trim(env_value('MANAGE_DATA_KEY', '')) !== '';
$wrongKey = $keyConfigured && trim((string) ($_GET['key'] ?? '')) !== '' && !$admin && empty($_SESSION['user_id']);

if ($admin === null) {
    $gateRole = (string) ($_SESSION['role'] ?? '');
    require __DIR__ . '/views/gate.php';
    exit;
}

$tab = ($_GET['tab'] ?? 'data') === 'receipts' ? 'receipts' : 'data';
$flash = null;
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
  if (!manage_data_csrf_ok($_POST['_csrf'] ?? null)) {
        $flash = 'Security token expired. Refresh and try again.';
        $flashType = 'bad';
    } elseif ($action === 'purge') {
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'DELETE') {
            $flash = 'Type DELETE in the confirmation box to proceed.';
            $flashType = 'bad';
        } else {
            $scopes = is_array($_POST['scopes'] ?? null) ? array_map('strval', $_POST['scopes']) : [];
            $allDates = !empty($_POST['all_dates']);
            $staffUser = (int) ($_POST['staff_user_id'] ?? 0);
            $mobile = (string) ($_POST['devotee_mobile'] ?? '');
            $result = manage_data_purge($scopes, $allDates, $_POST['from'] ?? null, $_POST['to'] ?? null, $staffUser, $mobile);
            $flash = $result['message'];
            $flashType = $result['ok'] ? 'ok' : 'bad';
        }
    } elseif ($action === 'receipt_delete') {
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'DELETE') {
            $flash = 'Type DELETE to remove selected PDFs.';
            $flashType = 'bad';
        } else {
            $result = manage_data_delete_receipt_files(
                is_array($_POST['files'] ?? null) ? array_map('strval', $_POST['files']) : [],
                !empty($_POST['delete_all_filtered']),
                $_POST['search'] ?? null,
                $_POST['from'] ?? null,
                $_POST['to'] ?? null
            );
            $flash = $result['message'];
            $flashType = $result['ok'] ? 'ok' : 'bad';
        }
    } elseif ($action === 'receipt_zip') {
        manage_data_stream_receipt_zip(is_array($_POST['files'] ?? null) ? array_map('strval', $_POST['files']) : []);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$from = manage_data_valid_date($_GET['from'] ?? null) ?? '';
$to = manage_data_valid_date($_GET['to'] ?? null) ?? '';
$receipts = $tab === 'receipts' ? manage_data_list_receipt_pdfs($search, $from !== '' ? $from : null, $to !== '' ? $to : null) : [];
$staffUsers = manage_data_staff_users();

$self = htmlspecialchars((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'), ENT_QUOTES, 'UTF-8');
$keyQ = $keyConfigured && isset($_GET['key']) ? '&key=' . rawurlencode((string) $_GET['key']) : '';
$csrf = manage_data_csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title>Manage data — <?= e(APP_NAME) ?></title>
  <?php app_favicon_link(); ?>
  <link rel="stylesheet" href="assets/manage.css?v=2">
</head>
<body>
  <div class="wrap">
    <header class="hdr">
      <div>
        <h1>Manage data (Admin)</h1>
        <p class="muted">Signed in as <?= e($admin['name']) ?> · standalone module (not in ERP menu)</p>
      </div>
    </header>

    <?php if ($flash !== null): ?>
      <div class="flash flash-<?= $flashType === 'ok' ? 'ok' : 'bad' ?>" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <nav class="tabs" aria-label="Sections">
      <a href="<?= $self ?>?tab=data<?= $keyQ ?>"<?= $tab === 'data' ? ' aria-current="page"' : '' ?>>Database records</a>
      <a href="<?= $self ?>?tab=receipts<?= $keyQ ?>"<?= $tab === 'receipts' ? ' aria-current="page"' : '' ?>>Receipt PDFs</a>
    </nav>

    <?php if ($tab === 'data'): ?>
      <section class="card">
        <div class="warn"><strong>Destructive.</strong> Removes rows from the shared MySQL database and related booking notifications, payments, and receipt PDFs where applicable. Does not remove users, services, or settings.</div>
        <form method="post" action="<?= $self ?>?tab=data<?= $keyQ ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="purge">
          <?php if ($keyConfigured): ?><input type="hidden" name="key" value="<?= e((string) ($_GET['key'] ?? '')) ?>"><?php endif; ?>

          <h2>What to remove</h2>
          <div class="checks">
            <label><input type="checkbox" name="scopes[]" value="donations"> CRM gifts / donations (ledger, cash book, bank links, receipt rows &amp; PDFs)</label>
            <label><input type="checkbox" name="scopes[]" value="bookings"> Online bookings (items, payments, WhatsApp notification queue, linked ERP donations)</label>
            <label><input type="checkbox" name="scopes[]" value="donation_intents"> Online donation payment intents (Booking/donation)</label>
            <label><input type="checkbox" name="scopes[]" value="subscription_invoices"> Subscription invoices (not subscribers themselves)</label>
          </div>

          <h2>Filter by user / devotee</h2>
          <p class="muted">Optional. Leave blank to match everyone in the date range.</p>
          <div class="grid-2">
            <div>
              <label for="staff_user_id">Recorded by (CRM staff user)</label>
              <select id="staff_user_id" name="staff_user_id">
                <option value="0">— Any staff user —</option>
                <?php foreach ($staffUsers as $u): ?>
                  <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['role']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="devotee_mobile">Devotee mobile (contains)</label>
              <input type="text" id="devotee_mobile" name="devotee_mobile" inputmode="numeric" placeholder="e.g. 98765" maxlength="15">
            </div>
          </div>

          <h2>Date range</h2>
          <p class="muted">Gifts use <em>donation_date</em>; bookings use <em>created_at</em>; intents use <em>created_at</em>; invoices use <em>due_date</em>. Staff filter: gifts (<em>created_by</em>), paid invoices (linked gift’s <em>created_by</em>).</p>
          <div class="grid-2">
            <div>
              <label for="from">From</label>
              <input type="date" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div>
              <label for="to">To</label>
              <input type="date" id="to" name="to" value="<?= e($to) ?>">
            </div>
          </div>
          <p style="margin-top:12px;">
            <label><input type="checkbox" name="all_dates" value="1"> Remove <strong>all dates</strong> (ignore From/To)</label>
          </p>

          <h2>Confirm</h2>
          <label for="confirm-purge">Type <code>DELETE</code></label>
          <input type="text" id="confirm-purge" name="confirm" autocomplete="off" placeholder="DELETE">

          <div class="actions">
            <button class="btn btn-danger" type="submit">Remove selected data</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <section class="card">
        <form method="get" action="<?= $self ?>" class="grid-2" style="align-items:end;margin-bottom:12px;">
          <input type="hidden" name="tab" value="receipts">
          <?php if ($keyConfigured && isset($_GET['key'])): ?>
            <input type="hidden" name="key" value="<?= e((string) $_GET['key']) ?>">
          <?php endif; ?>
          <div>
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Receipt no., ODN ref, purpose">
          </div>
          <div>
            <label for="rf">From</label>
            <input type="date" id="rf" name="from" value="<?= e($from) ?>">
          </div>
          <div>
            <label for="rt">To</label>
            <input type="date" id="rt" name="to" value="<?= e($to) ?>">
          </div>
          <div class="actions" style="margin:0;">
            <button class="btn" type="submit">Filter</button>
          </div>
        </form>

        <form method="post" action="<?= $self ?>?tab=receipts<?= $keyQ ?>" id="receipt-form">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="search" value="<?= e($search) ?>">
          <input type="hidden" name="from" value="<?= e($from) ?>">
          <input type="hidden" name="to" value="<?= e($to) ?>">
          <?php if ($keyConfigured): ?><input type="hidden" name="key" value="<?= e((string) ($_GET['key'] ?? '')) ?>"><?php endif; ?>

          <div class="table-scroll">
            <table class="data">
              <thead>
                <tr>
                  <th><input type="checkbox" id="pick-all" title="Select all shown"></th>
                  <th>Receipt</th>
                  <th>Source</th>
                  <th>Date</th>
                  <th>Size</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($receipts === []): ?>
                  <tr><td colspan="5" class="muted">No PDF files match.</td></tr>
                <?php else: ?>
                  <?php foreach ($receipts as $r): ?>
                    <tr>
                      <td><input type="checkbox" name="files[]" value="<?= e($r['file']) ?>" class="pick-row"></td>
                      <td><?= e($r['receipt_number']) ?></td>
                      <td><?= e($r['source']) ?></td>
                      <td><?= e((string) ($r['donation_date'] ?? '—')) ?></td>
                      <td><?= e(number_format($r['bytes'] / 1024, 1)) ?> KB</td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <p class="muted">Files live in <code>crm/storage/receipts/</code> (CRM, booking-linked, and online donations). Booking invoice PDFs are generated on demand and are not listed here.</p>

          <label for="confirm-rcpt">Type <code>DELETE</code> to delete PDFs</label>
          <input type="text" id="confirm-rcpt" name="confirm" autocomplete="off" placeholder="DELETE">

          <div class="actions">
            <button class="btn" type="submit" name="action" value="receipt_zip">Download selected (ZIP)</button>
            <button class="btn btn-danger" type="submit" name="action" value="receipt_delete">Delete selected PDFs</button>
            <button class="btn btn-danger" type="submit" name="action" value="receipt_delete" id="delete-all-filtered">Delete all in filter</button>
            <input type="hidden" name="delete_all_filtered" id="delete-all-flag" value="">
          </div>
        </form>
      </div>
      <script>
        var pickAll = document.getElementById('pick-all');
        if (pickAll) {
          pickAll.addEventListener('change', function () {
            document.querySelectorAll('.pick-row').forEach(function (c) { c.checked = pickAll.checked; });
          });
        }
        var delAllBtn = document.getElementById('delete-all-filtered');
        var delAllFlag = document.getElementById('delete-all-flag');
        if (delAllBtn && delAllFlag) {
          delAllBtn.addEventListener('click', function () { delAllFlag.value = '1'; });
        }
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
