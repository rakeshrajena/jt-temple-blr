<?php
declare(strict_types=1);

/**
 * Lists DB probe URLs and runs local CLI checks before upload.
 *   php crm/bin/env_deploy_probes.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$crmRoot = dirname(__DIR__);
$repoRoot = dirname($crmRoot);

require $crmRoot . '/includes/env.php';

echo "=== Production .env probes (open in browser after upload) ===\n";
echo "  https://sjtbengaluru.org/crm/install_db_probe.php\n";
echo "  https://sjtbengaluru.org/Booking/install_db_probe.php\n";
echo "  https://sjtbengaluru.org/Booking/donation/install_db_probe.php  (same as Booking)\n";
echo "  Streaming: no DB — only public_html/Streaming/.env STREAM_SAVE_TOKEN\n";
echo "\n=== Local effective credentials (ignores .env.local on server) ===\n";

$crmUser = env_first(['DB_USER', 'MYSQL_USER']);
$crmName = env_first(['DB_NAME', 'MYSQL_DATABASE']);
echo "crm DB_USER={$crmUser} DB_NAME={$crmName} local_dev=" . (env_local_dev_host() ? 'yes' : 'no') . "\n";

$bookingEnv = $repoRoot . '/Booking/.env';
if (is_readable($bookingEnv)) {
  $b = env_parse((string) file_get_contents($bookingEnv));
  echo 'Booking/.env DB_USER=' . trim($b['DB_USER'] ?? '') . ' (file only; runtime uses bootstrap)' . "\n";
}

$sync = $crmRoot . '/bin/verify_env_sync.php';
if (is_file($sync)) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($sync), $code);
    exit($code);
}
exit(0);