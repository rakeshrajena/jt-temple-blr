<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_script(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/jt_blr/jt-temple-blr/php/index.php'));
    return $script === '' ? '/jt_blr/jt-temple-blr/php/index.php' : $script;
}

function app_dir_url(): string
{
    return rtrim(str_replace('\\', '/', dirname(app_script())), '/');
}

function url(string $path = '', array $query = []): string
{
    $path = trim($path, '/');
    if ($path !== '') {
        $query = ['r' => $path] + $query;
    }
    $base = app_script();
    if ($query === []) {
        return $base;
    }
    return $base . '?' . http_build_query($query);
}

function absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    return app_dir_url() . '/static/' . ltrim($path, '/');
}

function request_path(): string
{
    $fromQuery = $_GET['r'] ?? null;
    if (is_string($fromQuery) && $fromQuery !== '') {
        return trim($fromQuery, '/');
    }
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $uri = is_string($uri) ? $uri : '/';
    $dir = app_dir_url();
    if ($dir !== '' && $dir !== '/' && str_starts_with($uri, $dir)) {
        $uri = substr($uri, strlen($dir));
    }
    $uri = trim($uri, '/');
    if ($uri === 'index.php') {
        return '';
    }
    return $uri;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(string $category, string $message): void
{
    $_SESSION['flashes'][] = [$category, $message];
}

/** @return list<array{0:string,1:string}> */
function pull_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        echo 'Invalid form token. Go back and submit the form again.';
        exit;
    }
}

function post_string(string $key, int $max = 255): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        return mb_substr($value, 0, $max);
    }
    return $value;
}

function one_of(string $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function post_date(string $key): string
{
    $value = post_string($key, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value) {
            return $value;
        }
    }
    return date('Y-m-d');
}

function money(mixed $amount, int $decimals = 0): string
{
    return '₹' . number_format((float) $amount, $decimals);
}

function money_or_dash(mixed $amount, int $decimals = 0): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return money($amount, $decimals);
}

function dash(mixed $value): string
{
    $text = trim((string) $value);
    return $text === '' ? '—' : $text;
}

function receipt_link(mixed $number): string
{
    $number = trim((string) $number);
    if (preg_match('/^RCPT-\d{4}-\d{4}$/', $number) !== 1) {
        return '';
    }
    $href = url('receipts/' . $number . '.pdf');
    return '<a class="badge badge-green" href="' . e($href) . '" target="_blank" rel="noopener">' . e($number) . '</a>';
}

/** @return array{id:?int,username:?string,full_name:?string,role:?string} */
function current_user(): array
{
    return [
        'id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? (string) $_SESSION['username'] : null,
        'full_name' => isset($_SESSION['full_name']) ? (string) $_SESSION['full_name'] : null,
        'role' => isset($_SESSION['role']) ? (string) $_SESSION['role'] : null,
    ];
}

function login_required(): void
{
    if (!isset($_SESSION['user_id'])) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? url(''));
        redirect(url('login', ['next' => $next]));
    }
}

function admin_required(): void
{
    login_required();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        flash('error', t('shell.admin_only'));
        redirect(url(''));
    }
}

function safe_next(?string $next): string
{
    if ($next === null || $next === '') {
        return url('');
    }
    $parts = parse_url($next);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return url('');
    }
    $path = $parts['path'] ?? '';
    $script = app_script();
    $dir = app_dir_url();
    $allowed = $path === $script || $path === $dir || $path === $dir . '/' || str_starts_with($path, $dir . '/');
    if (!$allowed) {
        return url('');
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $path . $query;
}

function render(string $template, array $vars = [], bool $useLayout = true): void
{
    $vars['currentUser'] = current_user();
    $vars['flashes'] = pull_flashes();
    $viewFile = APP_ROOT . '/views/' . $template . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Missing view: ' . $template);
    }
    // Isolate the template scope so a view variable named $data is not overwritten
    // by this function's own arguments.
    $include = static function (string $file, array $vars): void {
        extract($vars, EXTR_SKIP);
        require $file;
    };
    if (!$useLayout) {
        $include($viewFile, $vars);
        return;
    }
    ob_start();
    $include($viewFile, $vars);
    $content = ob_get_clean();
    $include(APP_ROOT . '/views/layout.php', $vars + ['content' => $content]);
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function ensure_storage(): void
{
    foreach (['uploads', 'receipts', 'coupons', 'logs', 'vouchers'] as $dir) {
        $path = APP_ROOT . '/storage/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Could not create storage directory: ' . $dir);
        }
    }
}

function notify_log(string $channel, string $to, string $message): void
{
    $line = sprintf("[%s] %s to %s: %s\n", date('c'), $channel, $to, str_replace(["\r", "\n"], ' ', $message));
    file_put_contents(APP_ROOT . '/storage/logs/notifications.log', $line, FILE_APPEND | LOCK_EX);
}
