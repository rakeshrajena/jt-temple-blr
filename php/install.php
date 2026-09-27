<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$ready = db_config_error() === null && strlen(env_value('INSTALL_TOKEN')) >= 16;
$result = null;
$error = db_config_error();
$booksReady = is_file(books_snapshot_path());
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
  </style>
</head>
<body>
<main>
  <h1>Set up the temple books</h1>
  <p>This updates the database that already exists. It creates any missing tables and adds the latest columns for receipts, coupons, invitations, approvals, and settings. It does not create a new database.</p>
  <?php if ($error !== null): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif; ?>
  <?php if (is_array($result)): ?>
    <div class="ok">
      <p><strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?> has <?= e((string) $result['tables']) ?> tables, <?= e((string) $result['users']) ?> users, and <?= e((string) $result['donors']) ?> devotees.</p>
      <p><?php if ($result['imported']): ?>
        The saved books from this copy were loaded, including devotees, gifts, stock, expenses, coupons, invitations, and settings.
      <?php elseif ($result['seeded']): ?>
        Demo data was loaded because this copy had no saved books and the database had no devotees yet.
      <?php else: ?>
        Devotees already in this database were left in place. The latest tables were still updated.
      <?php endif; ?></p>
      <p>Sign in at <a href="index.php">index.php</a>. Then delete <code>INSTALL_TOKEN</code> from the .env file so this page cannot be used again.</p>
    </div>
  <?php elseif ($ready): ?>
    <p>Target database: <strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?>.</p>
    <?php if ($booksReady): ?>
      <p>A saved copy of the books is included. <?php if ($existingDonors === 0): ?>It will be loaded because this database has no devotees yet.<?php else: ?>This database already has devotees, so they stay unless you choose to replace them.<?php endif; ?></p>
    <?php else: ?>
      <p>No saved books were found with this copy. An empty database will get the demo devotees instead.</p>
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
