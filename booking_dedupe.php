<?php
declare(strict_types=1);

/**
 * Temporary one-off: find and remove duplicate online-booking rows.
 * DELETE THIS FILE from the server after use.
 *
 * Access: signed-in ERP Admin/Treasurer, or ?token= matching INSTALL_TOKEN in .env.
 */
require __DIR__ . '/includes/bootstrap.php';

if (!booking_module_installed()) {
    http_response_code(503);
    exit('Online booking tables are not on this database. Run Booking migrations first.');
}

$user = current_user();
$tokenOk = install_token_matches((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
if (!$user && !$tokenOk) {
    http_response_code(403);
    exit('Sign in to the ERP as Admin, or open with ?token=YOUR_INSTALL_TOKEN (then delete this file).');
}
if ($user && !in_array($user['role'] ?? '', ['Admin', 'Treasurer'], true)) {
    http_response_code(403);
    exit('Only Admin or Treasurer can run this tool.');
}

/** @return list<array{key:string, keeper:int, duplicate_ids:list<int>, rows:list<array<string,mixed>>}> */
function booking_dedupe_service_groups(): array
{
    $rows = db_all('SELECT id, name, code, is_active, sort_order, created_at FROM services ORDER BY id');
    $byName = [];
    foreach ($rows as $r) {
        $key = mb_strtolower(trim((string) $r['name']));
        if ($key === '') {
            $key = 'id:' . $r['id'];
        }
        $byName[$key][] = $r;
    }
    $groups = [];
    foreach ($byName as $key => $list) {
        if (count($list) < 2) {
            continue;
        }
        usort($list, static fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $keeper = (int) $list[0]['id'];
        $dupes = [];
        foreach ($list as $i => $r) {
            if ($i === 0) {
                continue;
            }
            $dupes[] = (int) $r['id'];
        }
        $groups[] = ['key' => $key, 'keeper' => $keeper, 'duplicate_ids' => $dupes, 'rows' => $list];
    }
    return $groups;
}

/** @return list<array{key:string, service_id:int, keeper:int, duplicate_ids:list<int>, rows:list<array<string,mixed>>}> */
function booking_dedupe_slot_groups(): array
{
    $rows = db_all(
        'SELECT id, service_id, label, start_time, weekdays, capacity, is_active, sort_order FROM time_slots ORDER BY service_id, id'
    );
    $byKey = [];
    foreach ($rows as $r) {
        $key = (int) $r['service_id'] . '|' . mb_strtolower(trim((string) $r['label'])) . '|' . $r['start_time'] . '|' . $r['weekdays'];
        $byKey[$key][] = $r;
    }
    $groups = [];
    foreach ($byKey as $key => $list) {
        if (count($list) < 2) {
            continue;
        }
        usort($list, static fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $keeper = (int) $list[0]['id'];
        $dupes = [];
        foreach ($list as $i => $r) {
            if ($i === 0) {
                continue;
            }
            $dupes[] = (int) $r['id'];
        }
        $groups[] = [
            'key' => $key,
            'service_id' => (int) $list[0]['service_id'],
            'keeper' => $keeper,
            'duplicate_ids' => $dupes,
            'rows' => $list,
        ];
    }
    return $groups;
}

/** @return array{removed:int, skipped:int, messages:list<string>} */
function booking_dedupe_run(string $table): array
{
    $removed = 0;
    $skipped = 0;
    $messages = [];
    if ($table === 'time_slots') {
        foreach (booking_dedupe_slot_groups() as $g) {
            $keeper = $g['keeper'];
            foreach ($g['duplicate_ids'] as $dupId) {
                $refs = (int) db_value('SELECT COUNT(*) FROM booking_items WHERE slot_id = ?', [$dupId]);
                if ($refs > 0) {
                    db_exec('UPDATE booking_items SET slot_id = ? WHERE slot_id = ?', [$keeper, $dupId]);
                    $messages[] = "Slot #$dupId: moved $refs booking item(s) to slot #$keeper.";
                }
                db_exec('DELETE FROM time_slots WHERE id = ?', [$dupId]);
                $removed++;
            }
        }
        return compact('removed', 'skipped', 'messages');
    }
    if ($table === 'services') {
        foreach (booking_dedupe_service_groups() as $g) {
            $keeper = $g['keeper'];
            foreach ($g['duplicate_ids'] as $dupId) {
                $refs = (int) db_value('SELECT COUNT(*) FROM booking_items WHERE service_id = ?', [$dupId]);
                if ($refs > 0) {
                    db_exec('UPDATE booking_items SET service_id = ? WHERE service_id = ?', [$keeper, $dupId]);
                    $messages[] = "Service #$dupId: moved $refs booking item(s) to service #$keeper.";
                }
                db_exec('DELETE FROM time_slots WHERE service_id = ?', [$dupId]);
                db_exec('DELETE FROM blocked_dates WHERE service_id = ?', [$dupId]);
                try {
                    db_exec('DELETE FROM services WHERE id = ?', [$dupId]);
                    $removed++;
                } catch (Throwable $e) {
                    $skipped++;
                    $messages[] = "Service #$dupId: could not delete — " . $e->getMessage();
                }
            }
        }
        return compact('removed', 'skipped', 'messages');
    }
    return ['removed' => 0, 'skipped' => 0, 'messages' => ['Unknown table.']];
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        $result = ['error' => 'Session expired. Reload and try again.'];
    } else {
        $table = is_string($_POST['table'] ?? null) ? trim($_POST['table']) : '';
        if (!in_array($table, ['services', 'time_slots'], true)) {
            $result = ['error' => 'Choose services or time_slots.'];
        } elseif (empty($_POST['confirm'])) {
            $result = ['error' => 'Tick the confirmation box first.'];
        } else {
            $run = booking_dedupe_run($table);
            $result = [
                'ok' => true,
                'table' => $table,
                'removed' => $run['removed'],
                'skipped' => $run['skipped'],
                'messages' => $run['messages'],
            ];
        }
    }
}

$serviceGroups = booking_dedupe_service_groups();
$slotGroups = booking_dedupe_slot_groups();
$serviceDupeCount = array_sum(array_map(static fn ($g) => count($g['duplicate_ids']), $serviceGroups));
$slotDupeCount = array_sum(array_map(static fn ($g) => count($g['duplicate_ids']), $slotGroups));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="robots" content="noindex,nofollow">
  <title>Remove duplicate booking rows</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 960px; margin: 24px auto; padding: 0 16px; line-height: 1.45; }
    h1 { font-size: 1.35rem; }
    .warn { background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 8px; margin: 16px 0; }
    .ok { background: #d1e7dd; border: 1px solid #badbcc; padding: 12px; border-radius: 8px; margin: 16px 0; }
    .err { background: #f8d7da; border: 1px solid #f5c2c7; padding: 12px; border-radius: 8px; margin: 16px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 12px 0 24px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f5f5f5; }
    .keeper { background: #e8f5e9; }
    .dupe { background: #ffebee; }
    form { margin: 16px 0; padding: 16px; border: 1px solid #ddd; border-radius: 8px; }
    label.check { display: block; margin: 10px 0; }
    button { padding: 8px 16px; cursor: pointer; }
    .muted { color: #555; font-size: 13px; }
  </style>
</head>
<body>
  <h1>Remove duplicate online booking rows</h1>
  <p class="warn"><strong>Temporary tool.</strong> Keeps the <em>lowest id</em> in each duplicate group, re-points bookings if needed, then deletes extras. <strong>Delete <code>crm/booking_dedupe.php</code> on the server when finished.</strong></p>

  <?php if (is_array($result) && !empty($result['error'])): ?>
    <div class="err"><?= e($result['error']) ?></div>
  <?php elseif (is_array($result) && !empty($result['ok'])): ?>
    <div class="ok">
      Removed <strong><?= (int) $result['removed'] ?></strong> row(s) from <code><?= e($result['table']) ?></code>.
      <?php if ((int) $result['skipped'] > 0): ?> Skipped <?= (int) $result['skipped'] ?>.<?php endif; ?>
      <?php foreach ($result['messages'] as $m): ?><br><?= e($m) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="muted">Duplicates: <strong><?= (int) $serviceDupeCount ?></strong> extra service(s) in <?= count($serviceGroups) ?> group(s);
    <strong><?= (int) $slotDupeCount ?></strong> extra time slot(s) in <?= count($slotGroups) ?> group(s).</p>

  <h2>Services (<code>services</code>)</h2>
  <p class="muted">Same name (ignoring case/spaces) = duplicate. Keeps smallest <code>id</code>.</p>
  <?php if ($serviceGroups === []): ?>
    <p>No duplicate services found.</p>
  <?php else: ?>
    <?php foreach ($serviceGroups as $g): ?>
      <table>
        <tr><th colspan="5">Group: <?= e($g['key']) ?> — keep id <?= (int) $g['keeper'] ?></th></tr>
        <tr><th>id</th><th>name</th><th>code</th><th>active</th><th>sort</th></tr>
        <?php foreach ($g['rows'] as $r): ?>
          <tr class="<?= (int) $r['id'] === $g['keeper'] ? 'keeper' : 'dupe' ?>">
            <td><?= (int) $r['id'] ?></td>
            <td><?= e((string) $r['name']) ?></td>
            <td><?= e((string) $r['code']) ?></td>
            <td><?= (int) $r['is_active'] ? 'yes' : 'no' ?></td>
            <td><?= (int) $r['sort_order'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($tokenOk && !$user): ?><input type="hidden" name="token" value="<?= e((string) ($_GET['token'] ?? '')) ?>"><?php endif; ?>
      <input type="hidden" name="table" value="services">
      <label class="check"><input type="checkbox" name="confirm" value="1"> I reviewed the groups above and want to remove duplicate <strong>services</strong>.</label>
      <button type="submit">Remove duplicate services</button>
    </form>
  <?php endif; ?>

  <h2>Time slots (<code>time_slots</code>)</h2>
  <p class="muted">Same service + label + start time + weekdays = duplicate. Keeps smallest <code>id</code>.</p>
  <?php if ($slotGroups === []): ?>
    <p>No duplicate time slots found.</p>
  <?php else: ?>
    <?php foreach ($slotGroups as $g): ?>
      <table>
        <tr><th colspan="6">Service #<?= (int) $g['service_id'] ?> — keep slot id <?= (int) $g['keeper'] ?></th></tr>
        <tr><th>id</th><th>label</th><th>start</th><th>weekdays</th><th>cap</th><th>active</th></tr>
        <?php foreach ($g['rows'] as $r): ?>
          <tr class="<?= (int) $r['id'] === $g['keeper'] ? 'keeper' : 'dupe' ?>">
            <td><?= (int) $r['id'] ?></td>
            <td><?= e((string) $r['label']) ?></td>
            <td><?= e((string) $r['start_time']) ?></td>
            <td><?= e((string) $r['weekdays']) ?></td>
            <td><?= $r['capacity'] === null ? '∞' : (int) $r['capacity'] ?></td>
            <td><?= (int) $r['is_active'] ? 'yes' : 'no' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($tokenOk && !$user): ?><input type="hidden" name="token" value="<?= e((string) ($_GET['token'] ?? '')) ?>"><?php endif; ?>
      <input type="hidden" name="table" value="time_slots">
      <label class="check"><input type="checkbox" name="confirm" value="1"> I reviewed the groups above and want to remove duplicate <strong>time slots</strong>.</label>
      <button type="submit">Remove duplicate time slots</button>
    </form>
  <?php endif; ?>

  <p><a href="<?= e(url('settings') . '#ob-services') ?>">← Back to Online booking settings</a></p>
</body>
</html>
