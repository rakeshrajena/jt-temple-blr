<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/dbutil.php';
require __DIR__ . '/lib/purge.php';
require __DIR__ . '/lib/receipts.php';
require __DIR__ . '/lib/coupons.php';
require __DIR__ . '/lib/receipt_regen.php';

$admin = manage_data_admin();
$keyConfigured = trim(env_value('MANAGE_DATA_KEY', '')) !== '';
$wrongKey = $keyConfigured && trim((string) ($_GET['key'] ?? '')) !== '' && !$admin && empty($_SESSION['user_id']);

if ($admin === null) {
    $gateRole = (string) ($_SESSION['role'] ?? '');
    require __DIR__ . '/views/gate.php';
    exit;
}

$tabRaw = (string) ($_GET['tab'] ?? 'data');
$tab = match ($tabRaw) {
    'receipts', 'coupons', 'regen' => $tabRaw,
    default => 'data',
};
$regenResults = [];
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
    } elseif ($action === 'coupon_purge') {
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'DELETE') {
            $flash = 'Type DELETE to remove coupons and linked scan/donation rows.';
            $flashType = 'bad';
        } else {
            $result = manage_data_purge_coupons(
                (string) ($_POST['coupon_scope'] ?? ''),
                (string) ($_POST['coupon_input'] ?? ''),
                !empty($_POST['coupon_whole_batch'])
            );
            $flash = $result['message'];
            $flashType = $result['ok'] ? 'ok' : 'bad';
            if ($result['ok']) {
                $tab = 'coupons';
            }
        }
    } elseif ($action === 'receipt_regen') {
        $result = manage_data_regenerate_receipts(
            (string) ($_POST['receipt_refs'] ?? ''),
            !empty($_POST['only_missing'])
        );
        $flash = $result['message'];
        $flashType = $result['ok'] ? 'ok' : 'bad';
        $regenResults = $result['results'];
        $tab = 'regen';
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$from = manage_data_valid_date($_GET['from'] ?? null) ?? '';
$to = manage_data_valid_date($_GET['to'] ?? null) ?? '';
$receipts = $tab === 'receipts' ? manage_data_list_receipt_pdfs($search, $from !== '' ? $from : null, $to !== '' ? $to : null) : [];
$couponBatches = $tab === 'coupons' ? manage_data_coupon_batch_preview(manage_data_pdo()) : [];
$missingReceipts = $tab === 'regen' ? manage_data_missing_receipt_pdfs(manage_data_pdo()) : [];
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
  <title>Manage data — <?= e(function_exists('app_display_name') ? app_display_name() : APP_NAME) ?></title>
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
      <a href="<?= $self ?>?tab=regen<?= $keyQ ?>"<?= $tab === 'regen' ? ' aria-current="page"' : '' ?>>Recreate receipts</a>
      <a href="<?= $self ?>?tab=coupons<?= $keyQ ?>"<?= $tab === 'coupons' ? ' aria-current="page"' : '' ?>>Food coupons</a>
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
    <?php elseif ($tab === 'regen'): ?>
      <section class="card">
        <p class="muted">Rebuilds <code>storage/receipts/{RCPT-….}.pdf</code> from the existing gift row. Same receipt number, amounts, and status — nothing is inserted or updated in donations, ledger, cash book, or booking tables.</p>

        <?php if ($missingReceipts !== []): ?>
          <h2>Gifts with no PDF on disk</h2>
          <div class="table-scroll">
            <table class="data">
              <thead>
                <tr>
                  <th>Receipt</th>
                  <th>Date</th>
                  <th>Source</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($missingReceipts as $m): ?>
                  <tr>
                    <td><code><?= e($m['receipt_number']) ?></code></td>
                    <td><?= e((string) ($m['donation_date'] ?? '—')) ?></td>
                    <td><?= e($m['source']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= $self ?>?tab=regen<?= $keyQ ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="receipt_regen">
          <?php if ($keyConfigured): ?><input type="hidden" name="key" value="<?= e((string) ($_GET['key'] ?? '')) ?>"><?php endif; ?>

          <label for="receipt_refs">Receipt number or reference</label>
          <textarea id="receipt_refs" name="receipt_refs" rows="5" placeholder="RCPT-1736899200-0042&#10;ODN-20250927-XXXX&#10;BK-… booking reference"><?php if (!empty($_POST['receipt_refs']) && ($_POST['action'] ?? '') === 'receipt_regen'): ?><?= e((string) $_POST['receipt_refs']) ?><?php endif; ?></textarea>
          <p class="muted">One per line or comma-separated: <strong>RCPT-…</strong>, online donation <strong>ODN-…</strong>, booking ref, or numeric gift ID.</p>

          <p style="margin-top:12px;">
            <label><input type="checkbox" name="only_missing" value="1" checked> Only recreate when the PDF file is missing (skip if already on disk)</label>
          </p>

          <div class="actions">
            <button class="btn" type="submit">Recreate PDF(s)</button>
          </div>
        </form>

        <?php if ($regenResults !== []): ?>
          <h2>Last run</h2>
          <div class="table-scroll">
            <table class="data">
              <thead>
                <tr>
                  <th>You entered</th>
                  <th>Receipt no.</th>
                  <th>Result</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($regenResults as $r): ?>
                  <tr>
                    <td><?= e($r['ref']) ?></td>
                    <td><?= $r['number'] !== '' ? e($r['number']) : '—' ?></td>
                    <td><?= e($r['status']) ?></td>
                    <td><?= e($r['detail']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($tab === 'coupons'): ?>
      <section class="card">
        <div class="warn"><strong>Destructive.</strong> Removes food coupon rows, linked scan donations (ledger/cash book), receipts, and the batch approval. ERP “remove batch” refuses when coupons are redeemed — this tool removes them anyway.</div>

        <?php if ($couponBatches !== []): ?>
          <h2>Recent batches</h2>
          <div class="table-scroll">
            <table class="data">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Date</th>
                  <th>Qty</th>
                  <th>Approval</th>
                  <th>Scanned</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($couponBatches as $b): ?>
                  <tr>
                    <td><?= (int) $b['id'] ?></td>
                    <td><?= e((string) $b['coupon_name']) ?></td>
                    <td><?= e((string) $b['created_date']) ?></td>
                    <td><?= (int) $b['quantity'] ?></td>
                    <td><?= e((string) ($b['approval_status'] ?? '—')) ?></td>
                    <td><?= (int) ($b['redeemed'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= $self ?>?tab=coupons<?= $keyQ ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="coupon_purge">
          <?php if ($keyConfigured): ?><input type="hidden" name="key" value="<?= e((string) ($_GET['key'] ?? '')) ?>"><?php endif; ?>

          <h2>What to remove</h2>
          <div class="checks">
            <label><input type="radio" name="coupon_scope" value="codes" checked> By coupon code(s)</label>
            <label><input type="radio" name="coupon_scope" value="batch"> Whole batch (ID, name, or any code in batch)</label>
            <label><input type="radio" name="coupon_scope" value="all"> <strong>All</strong> coupon batches and every scanned/unscanned coupon</label>
          </div>

          <label for="coupon_input">Coupon code(s), batch ID, or batch name</label>
          <textarea id="coupon_input" name="coupon_input" rows="4" placeholder="CU-1790447400-0006&#10;CU-1790447400-0007&#10;Or batch: MahapRasad&#10;Or batch ID: 12"></textarea>
          <p class="muted">One or more lines. For <em>batch</em> mode you can enter batch ID (e.g. <code>12</code>), partial name (<code>MahapRasad</code>), or any serial such as <code>CU-1790447400-0001</code>.</p>

          <p style="margin-top:12px;">
            <label><input type="checkbox" name="coupon_whole_batch" value="1"> When using codes, delete the <strong>entire batch</strong> that contains the listed code(s) (all serials CU-…-0001 … CU-…-0200)</label>
          </p>

          <h2>Confirm</h2>
          <label for="confirm-coupon">Type <code>DELETE</code></label>
          <input type="text" id="confirm-coupon" name="confirm" autocomplete="off" placeholder="DELETE">

          <div class="actions">
            <button class="btn btn-danger" type="submit">Remove coupons &amp; linked donations</button>
          </div>
        </form>
      </section>
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
