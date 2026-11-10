<?php
declare(strict_types=1);

/**
 * Load CRM the same way as install/CLI paths so db() migrations never miss helpers.
 *
 * @param 'issue'|'email' $mode Kept for callers; full bootstrap covers both.
 */
function donation_erp_bootstrap(string $erpRoot, string $mode = 'issue'): void
{
    static $done = false;
    if ($done) {
        return;
    }
    unset($mode);

    if (!defined('JT_INSTALL_MINIMAL')) {
        define('JT_INSTALL_MINIMAL', true);
    }

    if (defined('APP_ROOT') && realpath((string) APP_ROOT) !== realpath($erpRoot)) {
        throw new RuntimeException('Cannot load ERP bootstrap: APP_ROOT is already set to Booking.');
    }

    if (!defined('APP_ROOT')) {
        define('APP_ROOT', $erpRoot);
    }

    require APP_ROOT . '/includes/bootstrap.php';
    $done = true;
}
