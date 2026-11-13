<?php
declare(strict_types=1);

/** Optional extra gate: set MANAGE_DATA_KEY in crm/.env and pass ?key=… when opening this module. */
function manage_data_key_ok(): bool
{
    $expected = trim(env_value('MANAGE_DATA_KEY', ''));
    if ($expected === '') {
        return true;
    }
    $given = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
    return $given !== '' && hash_equals($expected, $given);
}

/** @return array{id:int,name:string,username:string}|null */
function manage_data_admin(): ?array
{
    if (!manage_data_key_ok()) {
        return null;
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if ((string) ($_SESSION['role'] ?? '') !== 'Admin') {
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) ($_SESSION['full_name'] ?? ''),
        'username' => (string) ($_SESSION['username'] ?? ''),
    ];
}

function manage_data_csrf_token(): string
{
    if (empty($_SESSION['manage_data_csrf'])) {
        $_SESSION['manage_data_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['manage_data_csrf'];
}

function manage_data_csrf_ok(?string $token): bool
{
    $session = (string) ($_SESSION['manage_data_csrf'] ?? '');
    return $token !== null && $token !== '' && $session !== '' && hash_equals($session, $token);
}
