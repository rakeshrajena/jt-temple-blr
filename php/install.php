<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'PHP 8.1 or newer is needed; this server runs ' . PHP_VERSION . ". Choose PHP 8.2 in the hosting control panel (cPanel, Select PHP Version), then reload this page.\n";
    exit;
}

require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$serverProblems = install_requirement_problems(install_server_facts());
$ready = $serverProblems === [] && db_config_error() === null && strlen(env_value('INSTALL_TOKEN')) >= 16;
$result = null;
$error = db_config_error();
$booksSummary = books_snapshot_summary(books_snapshot_path());
$booksReady = $booksSummary !== null;
$existingDonors = null;
if ($error === null && strlen(env_value('INSTALL_TOKEN')) < 16) {
    $error = 'Add INSTALL_TOKEN to the .env file. Use at least 16 random characters, then reload this page.';
}
if ($error === null) {
    try {
        $look = db_connect();
        $hasUsers = $look->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($hasUsers !== false && $look->query("SHOW TABLES LIKE 'donors'")->fetchColumn() !== false) {
            $existingDonors = (int) $look->query('SELECT COUNT(*) FROM donors')->fetchColumn();
        } else {
            $existingDonors = 0;
        }
    } catch (Throwable $e) {
        error_log('[jt_blr] install: ' . $e->getMessage());
        $error = 'Could not connect to the database. Check DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in the .env file.';
        $ready = false;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $ready) {
    require_csrf();
    $attempts = (int) ($_SESSION['install_attempts'] ?? 0);
    if ($attempts >= 8) {
        $error = 'Too many setup attempts. Close the browser and try again later.';
    } elseif (!install_token_matches((string) ($_POST['setup_key'] ?? ''))) {
        $_SESSION['install_attempts'] = $attempts + 1;
        $error = 'That setup key does not match the .env file.';
    } else {
        $_SESSION['install_attempts'] = 0;
        try {
            $result = install_database(isset($_POST['replace_books']));
        } catch (PDOException $e) {
            error_log('[jt_blr] install: ' . $e->getMessage());
            $error = 'Could not connect or create the tables. Check DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in the .env file.';
        } catch (Throwable $e) {
            error_log('[jt_blr] install: ' . $e->getMessage());
            $error = 'Could not finish setup. The error was written to the server log.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up the temple books</title>
  <style>
    body { margin: 0; font: 16px/1.5 Georgia, serif; background: #f6f1e7; color: #2a2118; }
    main { max-width: 36rem; margin: 8vh auto; padding: 2rem; background: #fffdf8; border: 1px solid #e4d3b4; border-radius: 12px; }
    h1 { font-size: 1.6rem; margin: 0 0 0.5rem; }
    p { margin: 0 0 1rem; }
    label { display: block; font-size: 0.9rem; margin-bottom: 0.35rem; }
    input { width: 100%; box-sizing: border-box; padding: 0.7rem 0.8rem; border: 1px solid #c8b48a; border-radius: 8px; font: inherit; }
    button { margin-top: 1rem; background: #7a1626; color: #fff; border: 0; border-radius: 8px; padding: 0.75rem 1.1rem; font: inherit; cursor: pointer; }
    .error { background: #fde8e4; border: 1px solid #e7b2a8; padding: 0.75rem 1rem; border-radius: 8px; }
    .ok { background: #e7f5ea; border: 1px solid #b7d7bf; padding: 0.75rem 1rem; border-radius: 8px; }
    code { font-family: Consolas, monospace; font-size: 0.92em; }
    label.check { margin: 0 0 1rem; }
    ol { margin: 0 0 1rem; padding-left: 1.3rem; }
    li { margin: 0 0 0.35rem; }
  </style>
</head>
<body>
<main>
  <h1>Set up the temple books</h1>
  <p>This updates the database that already exists. It does not create a new database.</p>
  <ol>
    <li>Missing tables and columns are added, including donation edits. An edit from the last 24 hours follows the Treasurer limit. An older edit needs both a Treasurer and an Admin.</li>
    <li>The saved books are loaded only when this database has no devotees, or when you tick replace. Receipts already generated keep their numbers. Their PDFs travel in <code>storage/receipts</code>; any PDF that did not upload is made again during setup. Use Generate receipt after sign-in for any other gift that should have one.</li>
    <li>Coupons are under Temple. One form generates them. Quantity 1 prints at once and does not wait for approval. A larger quantity still does. The amount follows the coupon name. Choosing a purpose does not change it.</li>
    <li>App contributors is a new list. Everyone signed in can read the cards. Only an Admin can add, update, or remove a person. The list starts empty.</li>
    <li>Puja purposes and their amounts travel in <code>storage/selections.json</code> with this folder. They are form choices, not rows in the database. Uploading this folder replaces that file on the server.</li>
    <li>Subscribers have an Update button. Their status is Active, Paused, Inactive, or Cancelled. Only an Active subscriber gets a new invoice. Each status change records who made it and when.</li>
    <li>Form instructions sit behind a <strong>?</strong> icon beside each heading or field. Point at it, or tap it on a phone, to read the steps line by line.</li>
    <li><strong>+ Invoice</strong> emails the subscriber a payment request with the plan, period, amount, due date, and a pay or donate link, once outgoing mail is saved under Settings.</li>
    <li>When a subscriber pays, the receipt is made at once in the same format as a donation receipt. <strong>Send receipt</strong> on the invoice emails the PDF to that subscriber.</li>
    <li>Invoices can be filtered by name, phone, status, period, due date, and receipt. Long tables have a Filter this table box. Receipt PDF text now wraps inside the border.</li>
  </ol>
  <?php if ($serverProblems !== []): ?>
    <div class="error">
      <p><strong>This server needs a change before setup can run:</strong></p>
      <ul>
        <?php foreach ($serverProblems as $problem): ?>
        <li><?= e($problem) ?></li>
        <?php endforeach; ?>
      </ul>
      <p>Reload this page after the change.</p>
    </div>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif; ?>
  <?php if (is_array($result)): ?>
    <div class="ok">
      <p><strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?> has <?= e((string) $result['tables']) ?> tables, <?= e((string) $result['users']) ?> users, and <?= e((string) $result['donors']) ?> devotees.</p>
      <p><?php if ($result['imported']): ?>
        The saved books from this copy were loaded, including devotees, gifts, stock, expenses, coupons, invitations, subscribers with their status history and invoices, receipts, and settings. Missing receipt PDFs were made again. Open Donations and use Generate receipt for any other gift that should have one. Coupons are under Temple. Puja purposes and their amounts are already in this folder’s form choices.
      <?php elseif ($result['seeded']): ?>
        Demo data was loaded because this copy had no saved books and the database had no devotees yet.
      <?php else: ?>
        Devotees already in this database were left in place. The latest tables were still updated. Puja purposes and their amounts are in this folder’s form choices, not in the devotee records.
      <?php endif; ?></p>
      <p>Sign in at <a href="index.php">index.php</a>. Then delete <code>INSTALL_TOKEN</code> from the .env file so this page cannot be used again.</p>
    </div>
  <?php elseif ($ready): ?>
    <p>Target database: <strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?>.</p>
    <?php if ($booksReady): ?>
      <p>A saved copy of the books is included: <?= e(number_format($booksSummary['rows'])) ?> rows from <?= e((string) $booksSummary['tables']) ?> tables with data, saved <?= e(date('j M Y, g:i a', $booksSummary['saved_at'])) ?>. <?= $booksSummary['receipts'] === 0 ? 'No gift in that copy has a receipt yet.' : e(number_format($booksSummary['receipts'])) . ($booksSummary['receipts'] === 1 ? ' gift has' : ' gifts have') . ' a generated receipt; the rest have none yet.' ?> <?php if ($existingDonors === 0): ?>It will be loaded because this database has no devotees yet.<?php else: ?>This database already has devotees. Tick the box below to replace them with this copy. Leave it unticked and only the new tables and columns are added.<?php endif; ?> Form choices, including each puja purpose and its amount, are already in <code>storage/selections.json</code> and do not depend on that tick.</p>
    <?php else: ?>
      <p>No readable saved books were found with this copy. An empty database will get the demo devotees instead.</p>
    <?php endif; ?>
    <form method="post" action="install.php">
      <?= csrf_field() ?>
      <?php if ($booksReady && $existingDonors !== null && $existingDonors > 0): ?>
        <label class="check"><input type="checkbox" name="replace_books" value="1"> Replace the devotees and books already in this database with the saved copy.</label>
      <?php endif; ?>
      <label for="setup_key">Setup key</label>
      <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>
      <button type="submit">Update tables and load the books</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
