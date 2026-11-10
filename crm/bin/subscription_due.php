<?php
declare(strict_types=1);

/** CLI/cron: mark overdue invoices and create this period's invoices for active subscribers. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('JT_INSTALL_MINIMAL', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

$result = subscription_auto_issue_due_invoices();
fwrite(STDOUT, 'Created: ' . $result['created'] . ', emailed: ' . $result['notified'] . PHP_EOL);
if ($result['errors'] !== []) {
    fwrite(STDERR, 'Errors: ' . implode(', ', $result['errors']) . PHP_EOL);
    exit(1);
}
exit(0);
