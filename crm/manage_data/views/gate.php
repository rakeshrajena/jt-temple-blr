<?php /** @var bool $wrongKey */ /** @var string $gateRole */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Manage data — Admin only</title>
  <?php app_favicon_link(); ?>
  <link rel="stylesheet" href="assets/manage.css">
</head>
<body>
  <div class="gate">
    <div class="card">
      <h1>Manage data</h1>
      <?php if (!empty($wrongKey)): ?>
        <p class="flash flash-bad">Invalid access key.</p>
      <?php elseif (!empty($gateRole) && $gateRole !== 'Admin'): ?>
        <p class="flash flash-bad">Your account is <?= e($gateRole) ?>. This module is for <strong>Admin</strong> only.</p>
      <?php endif; ?>
      <p>Log in as <strong>Admin</strong> in the temple CRM, then open this page again.</p>
      <p class="muted">After login you can use <strong>Administration → Manage data</strong> in the CRM sidebar.</p>
      <?php
        $returnPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: manage_data_url());
        $returnQuery = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        if (is_string($returnQuery) && $returnQuery !== '') {
            $returnPath .= '?' . $returnQuery;
        }
        $loginHref = crm_route_url('login', ['next' => $returnPath]);
      ?>
      <p><a class="btn btn-primary" href="<?= e($loginHref) ?>">Go to CRM login</a></p>
      <p class="muted">Correct URL: <code><?= e(manage_data_url()) ?></code> — not <code>?r=manage_data</code> on the main CRM page.</p>
    </div>
  </div>
</body>
</html>
