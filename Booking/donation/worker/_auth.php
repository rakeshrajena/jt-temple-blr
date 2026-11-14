<?php
declare(strict_types=1);

/** Shared auth for donation HTTP workers (no full bootstrap — same .env rules as Booking). */
function donation_worker_verify(): void
{
    require_once dirname(__DIR__, 2) . '/app/autoload.php';
    sjt_booking_load_env();

    $secret = trim((string) \SJT\Core\Env::get('APP_KEY', ''));
    if ($secret === '') {
        $secret = trim((string) \SJT\Core\Env::get('INSTALL_TOKEN', ''));
    }

    $got = (string) ($_SERVER['HTTP_X_SJT_DONATION_WORKER'] ?? $_POST['token'] ?? $_GET['token'] ?? '');
    if ($secret === '' || $got === '' || !hash_equals($secret, $got)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
}

function donation_worker_erp_root(): string
{
    $bookingRoot = dirname(__DIR__, 2);
    $repoRoot = dirname($bookingRoot);
    foreach (['crm', 'php'] as $name) {
        $path = $repoRoot . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path)) {
            return $path;
        }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ERP folder not found.';
    exit;
}