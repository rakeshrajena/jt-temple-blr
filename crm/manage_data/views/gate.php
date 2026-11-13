<?php /** @var bool $wrongKey */ /** @var string $gateRole */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Manage data — Admin only</title>
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
      <p class="muted">This tool is not linked from the ERP menu. Bookmark the URL after you sign in.</p>
      <?php
        $crmLogin = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/crm/manage_data'))), '/');
        if ($crmLogin === '' || $crmLogin === '.') {
            $crmLogin = '/crm';
        }
      ?>
      <p><a class="btn btn-primary" href="<?= e($crmLogin) ?>/index.php?r=login">Go to CRM login</a></p>
    </div>
  </div>
</body>
</html>
