<?php
declare(strict_types=1);

/**
 * Simulate document-root deploy (public_html/) and verify CRM URL helpers.
 *   php bin/prod_url_smoke.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('JT_INSTALL_MINIMAL', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;
$fail = static function (string $msg) use (&$failed): void {
    $failed++;
    fwrite(STDOUT, "FAIL {$msg}\n");
};
$ok = static function (string $msg): void {
    fwrite(STDOUT, "ok  {$msg}\n");
};

$_SERVER['SCRIPT_NAME'] = '/crm/index.php';
$_SERVER['HTTP_HOST'] = 'sjtbengaluru.org';
$_SERVER['HTTPS'] = 'on';

$expectCrm = '/crm';
$expectManage = '/crm/manage_data/';
$expectIndex = '/crm/index.php';

if (crm_erp_dir_url() !== $expectCrm) {
    $fail('crm_erp_dir_url() = ' . crm_erp_dir_url() . " (expected {$expectCrm})");
} else {
    $ok('crm_erp_dir_url() at domain root');
}

if (manage_data_url() !== $expectManage) {
    $fail('manage_data_url() = ' . manage_data_url() . " (expected {$expectManage})");
} else {
    $ok('manage_data_url() at domain root');
}

$login = crm_route_url('login');
if (!str_starts_with($login, $expectIndex)) {
    $fail('crm_route_url(login) = ' . $login);
} else {
    $ok('crm_route_url(login)');
}

$_SERVER['SCRIPT_NAME'] = '/crm/manage_data/index.php';
define('MANAGE_DATA_BOOT', true);

if (crm_erp_dir_url() !== $expectCrm) {
    $fail('manage_data crm_erp_dir_url() = ' . crm_erp_dir_url());
} else {
    $ok('manage_data boot: crm_erp_dir_url()');
}

if (manage_data_url() !== $expectManage) {
    $fail('manage_data boot: manage_data_url() = ' . manage_data_url());
} else {
    $ok('manage_data boot: manage_data_url()');
}

exit($failed > 0 ? 1 : 0);