<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$ready = db_config_error() === null && strlen(env_value('INSTALL_TOKEN')) >= 16;
$result = null;
$error = db_config_error();
if ($error === null && strlen(env_value('INSTALL_TOKEN')) < 16) {
    $error = 'Add INSTALL_TOKEN to the .env file. Use at least 16 random characters, then reload this page.';
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
            $result = install_database();
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
  </style>
</head>
<body>
<main>
  <h1>Set up the temple books</h1>
  <p>This creates the tables and demo data in the database that already exists. It does not create a new database, and it does not replace data that is already there.</p>
  <?php if ($error !== null): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif; ?>
  <?php if (is_array($result)): ?>
    <div class="ok">
      <p><strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?> has <?= e((string) $result['tables']) ?> tables and <?= e((string) $result['users']) ?> users.</p>
      <p><?= $result['seeded']
            ? 'Demo data was loaded for devotees, donations, stock, expenses, books, coupons, and the other tables.'
            : 'Demo data was left unchanged because devotees are already in the database.' ?></p>
      <p>Sign in at <a href="index.php">index.php</a>. Then delete <code>INSTALL_TOKEN</code> from the .env file so this page cannot be used again.</p>
    </div>
  <?php elseif ($ready): ?>
    <p>Target database: <strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?>.</p>
    <form method="post" action="install.php">
      <?= csrf_field() ?>
      <label for="setup_key">Setup key</label>
      <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>
      <button type="submit">Create tables and demo data</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
