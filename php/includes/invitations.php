<?php
declare(strict_types=1);

function invitation_send_limit(): int
{
    return 100;
}

function invitation_image_dir(): string
{
    return APP_ROOT . '/storage/invitations';
}

function ensure_invitation_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invitations (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            title        VARCHAR(120) NOT NULL,
            subject      VARCHAR(160) NOT NULL,
            blocks_json  MEDIUMTEXT NOT NULL,
            created_by   INT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB'
    );
}

/** @return list<array<string, mixed>> */
function invitation_rows(): array
{
    return db_all('SELECT id, title, subject, updated_at FROM invitations ORDER BY updated_at DESC, id DESC');
}

/** @return array<string, mixed>|null */
function invitation_row(int $id): ?array
{
    $row = db_one('SELECT * FROM invitations WHERE id = ?', [$id]);
    return is_array($row) ? $row : null;
}

function invitation_safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/[\r\n\s]/', $url) === 1 || strlen($url) > 500) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }
    return $url;
}

function invitation_clean_text(string $html): string
{
    $html = str_replace(["\r\n", "\r"], "\n", $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
    $html = preg_replace('/<\/(div|p)>/i', "\n", $html) ?? $html;
    $html = strip_tags($html, '<strong><em><b><i>');
    $html = preg_replace('/<(strong|em|b|i)\b[^>]*>/i', '<$1>', $html) ?? '';
    $html = str_ireplace(
        ['<b>', '</b>', '<i>', '</i>'],
        ['<strong>', '</strong>', '<em>', '</em>'],
        $html
    );
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($plain) > 2000) {
        return htmlspecialchars(mb_substr($plain, 0, 2000), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $tokens = [];
    $html = preg_replace_callback('/<\/?(?:strong|em)>/i', static function (array $match) use (&$tokens): string {
        $tag = strtolower($match[0]);
        $key = '%%T' . count($tokens) . '%%';
        $tokens[$key] = $tag;
        return $key;
    }, $html) ?? $html;
    $html = htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return trim(strtr($html, $tokens));
}

/**
 * @return array{blocks: list<array<string, string>>, error: ?string}
 */
function invitation_blocks_from_json(string $json, bool $requireImages = true): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return ['blocks' => [], 'error' => 'The invitation design could not be read.'];
    }
    if (count($decoded) > 16) {
        return ['blocks' => [], 'error' => 'An invitation can have up to 16 parts.'];
    }
    $blocks = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            return ['blocks' => [], 'error' => 'The invitation design could not be read.'];
        }
        $type = (string) ($item['type'] ?? '');
        if ($type === 'text') {
            $text = invitation_clean_text((string) ($item['html'] ?? ''));
            if (trim(strip_tags($text)) === '') {
                continue;
            }
            $align = (string) ($item['align'] ?? 'left');
            $kind = (string) ($item['kind'] ?? 'body');
            $blocks[] = [
                'type' => 'text',
                'html' => $text,
                'align' => in_array($align, ['left', 'center', 'right'], true) ? $align : 'left',
                'kind' => $kind === 'heading' ? 'heading' : 'body',
            ];
            continue;
        }
        if ($type === 'image') {
            $token = strtolower((string) ($item['token'] ?? ''));
            if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1 || invitation_image_path($token) === null) {
                if ($requireImages) {
                    return ['blocks' => [], 'error' => 'One picture in the invitation is missing. Add it again.'];
                }
                continue;
            }
            $alt = trim(strip_tags((string) ($item['alt'] ?? '')));
            $blocks[] = [
                'type' => 'image',
                'token' => $token,
                'alt' => mb_substr($alt, 0, 120),
            ];
            continue;
        }
        if ($type === 'video') {
            $url = invitation_safe_url((string) ($item['url'] ?? ''));
            if ($url === '') {
                return ['blocks' => [], 'error' => 'A video link must start with http:// or https://.'];
            }
            $label = trim(strip_tags((string) ($item['label'] ?? '')));
            $blocks[] = [
                'type' => 'video',
                'url' => $url,
                'label' => $label !== '' ? mb_substr($label, 0, 80) : 'Watch',
            ];
            continue;
        }
        return ['blocks' => [], 'error' => 'The invitation design could not be read.'];
    }
    return ['blocks' => $blocks, 'error' => null];
}

/** @param list<array<string, string>> $blocks */
function invitation_has_content(array $blocks): bool
{
    return $blocks !== [];
}

function invitation_image_path(string $token): ?string
{
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return null;
    }
    foreach (['jpg', 'png', 'gif', 'webp'] as $ext) {
        $path = invitation_image_dir() . '/' . $token . '.' . $ext;
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function invitation_image_mime(string $path): string
{
    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/png',
    };
}

/** @return array{token: ?string, error: ?string} */
function invitation_store_upload(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['token' => null, 'error' => 'Choose a picture.'];
    }
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        return ['token' => null, 'error' => 'That picture could not be saved.'];
    }
    if ((int) ($file['size'] ?? 0) > 2000000) {
        return ['token' => null, 'error' => 'Use a picture smaller than 2 MB.'];
    }
    $token = invitation_store_image_file($file['tmp_name']);
    if ($token === null) {
        return ['token' => null, 'error' => 'Use a JPEG, PNG, GIF, or WebP picture.'];
    }
    return ['token' => $token, 'error' => null];
}

function invitation_store_image_file(string $source): ?string
{
    if (!is_file($source)) {
        return null;
    }
    $info = @getimagesize($source);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($source);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!is_array($info) || !is_string($mime) || !isset($allowed[$mime])) {
        return null;
    }
    $dir = invitation_image_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }
    $token = bin2hex(random_bytes(16));
    $dest = $dir . '/' . $token . '.' . $allowed[$mime];
    if (!copy($source, $dest)) {
        return null;
    }
    return $token;
}

function invitation_delete_image(string $token): void
{
    $path = invitation_image_path($token);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
}

/** @param list<array<string, string>> $blocks */
function invitation_email_inner(array $blocks, string $devoteeName): string
{
    $name = htmlspecialchars(trim($devoteeName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<p style="margin:0 0 16px;font-size:16px;">Namaskar ' . $name . ',</p>';
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? '');
        if ($type === 'text') {
            $align = (string) ($block['align'] ?? 'left');
            $size = ($block['kind'] ?? '') === 'heading' ? '22px' : '16px';
            $weight = ($block['kind'] ?? '') === 'heading' ? '700' : '400';
            $color = ($block['kind'] ?? '') === 'heading' ? '#7A1626' : '#222222';
            $body = nl2br((string) ($block['html'] ?? ''), false);
            $html .= '<div style="margin:0 0 14px;text-align:' . $align . ';font-size:' . $size
                . ';font-weight:' . $weight . ';color:' . $color . ';line-height:1.45;">' . $body . '</div>';
            continue;
        }
        if ($type === 'image') {
            $token = (string) ($block['token'] ?? '');
            $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<p style="margin:0 0 14px;text-align:center;"><img src="cid:inv' . $token
                . '" alt="' . $alt . '" style="max-width:100%;height:auto;border:0;"></p>';
            continue;
        }
        if ($type === 'video') {
            $url = htmlspecialchars((string) ($block['url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars((string) ($block['label'] ?? 'Watch'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<p style="margin:0 0 14px;text-align:center;">'
                . '<a href="' . $url . '" style="display:inline-block;background:#7A1626;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;">'
                . $label . '</a></p>';
        }
    }
    return $html;
}

/** @param list<array<string, string>> $blocks */
function invitation_email_html(array $blocks, string $devoteeName, bool $withLogo): string
{
    return '<!DOCTYPE html><html><body style="margin:0;padding:16px;background:#f3f0ea;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e6dcc8;border-radius:12px;">'
        . '<tr><td style="padding:24px;font-family:Georgia,serif;color:#222222;">'
        . invitation_email_inner($blocks, $devoteeName)
        . email_signature_html($withLogo)
        . '</td></tr></table></td></tr></table></body></html>';
}

/** @param list<array<string, string>> $blocks */
function invitation_email_plain(array $blocks, string $devoteeName): string
{
    $lines = ['Namaskar ' . trim($devoteeName) . ','];
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? '');
        if ($type === 'text') {
            $lines[] = trim(html_entity_decode(strip_tags((string) ($block['html'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif ($type === 'video') {
            $lines[] = (string) ($block['label'] ?? 'Watch') . ': ' . (string) ($block['url'] ?? '');
        } elseif ($type === 'image') {
            $lines[] = '[Picture]';
        }
    }
    return implode("\n\n", array_filter($lines, static fn (string $line): bool => $line !== ''));
}

/**
 * @param list<array<string, string>> $blocks
 * @return list<array{cid:string,mime:string,bytes:string,filename:string}>
 */
function invitation_inline_images(array $blocks): array
{
    $images = [];
    foreach ($blocks as $block) {
        if (($block['type'] ?? '') !== 'image') {
            continue;
        }
        $token = (string) ($block['token'] ?? '');
        $path = invitation_image_path($token);
        if ($path === null) {
            continue;
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            continue;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $images[] = [
            'cid' => 'inv' . $token,
            'mime' => invitation_image_mime($path),
            'bytes' => $bytes,
            'filename' => 'invitation-' . $token . '.' . $ext,
        ];
    }
    return $images;
}

/** @return list<array{id:int,name:string,email:string}> */
function invitation_email_donors(): array
{
    $rows = db_all(
        "SELECT id, name, email FROM donors
         WHERE email IS NOT NULL AND TRIM(email) <> ''
         ORDER BY name, id"
    );
    $donors = [];
    foreach ($rows as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            continue;
        }
        $donors[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => $email,
        ];
    }
    return $donors;
}

function invitation_send_error(int $count, bool $ready, bool $hasContent): ?string
{
    if (!$hasContent) {
        return 'Add words, a picture, or a video link before sending.';
    }
    if ($count < 1) {
        return 'Select at least one devotee who has an email address.';
    }
    if ($count > invitation_send_limit()) {
        return 'Select up to ' . invitation_send_limit() . ' devotees at a time.';
    }
    if (!$ready) {
        return 'Outgoing mail is not configured.';
    }
    return null;
}

/** @param list<string> $keptTokens */
function invitation_drop_images(array $previousBlocks, array $keptTokens): void
{
    foreach ($previousBlocks as $block) {
        if (!is_array($block) || ($block['type'] ?? '') !== 'image') {
            continue;
        }
        $token = (string) ($block['token'] ?? '');
        if ($token !== '' && !in_array($token, $keptTokens, true)) {
            invitation_delete_image($token);
        }
    }
}

function dispatch_invitations(string $method, string $path): void
{
    if ($path === 'invitations' && $method === 'GET') {
        action_invitations();
        return;
    }
    if ($path === 'invitations' && $method === 'POST') {
        action_invitation_create();
        return;
    }
    if (preg_match('#^invitations/media/([a-f0-9]{32})$#', $path, $match) === 1 && $method === 'GET') {
        action_invitation_media($match[1]);
        return;
    }
    if (preg_match('#^invitations/(\d+)/image$#', $path, $match) === 1 && $method === 'POST') {
        action_invitation_image((int) $match[1]);
        return;
    }
    if (preg_match('#^invitations/(\d+)/send$#', $path, $match) === 1) {
        if ($method === 'POST') {
            action_invitation_send((int) $match[1]);
            return;
        }
        action_invitation_send_page((int) $match[1]);
        return;
    }
    if (preg_match('#^invitations/(\d+)/delete$#', $path, $match) === 1 && $method === 'POST') {
        action_invitation_delete((int) $match[1]);
        return;
    }
    if (preg_match('#^invitations/(\d+)$#', $path, $match) === 1) {
        if ($method === 'POST') {
            action_invitation_save((int) $match[1]);
            return;
        }
        action_invitation_edit((int) $match[1]);
        return;
    }
    http_response_code(404);
    echo 'Not found';
}

function action_invitations(): void
{
    login_required();
    render('invitations', [
        'title' => 'Invitations',
        'pageTitle' => 'Invitations',
        'active' => 'invitations',
        'invitations' => invitation_rows(),
    ]);
}

function action_invitation_create(): void
{
    login_required();
    $title = post_string('title', 120);
    if ($title === '') {
        flash('error', 'Name the invitation.');
        redirect(url('invitations'));
    }
    $userId = current_user()['id'];
    db_exec(
        'INSERT INTO invitations (title, subject, blocks_json, created_by) VALUES (?,?,?,?)',
        [$title, $title, '[]', $userId]
    );
    redirect(url('invitations/' . db()->lastInsertId()));
}

function action_invitation_edit(int $id): void
{
    login_required();
    $row = invitation_row($id);
    if ($row === null) {
        flash('error', 'That invitation was not found.');
        redirect(url('invitations'));
    }
    $parsed = invitation_blocks_from_json((string) $row['blocks_json'], false);
    if ($parsed['error'] !== null) {
        flash('error', $parsed['error']);
    }
    render('invitation_edit', [
        'title' => (string) $row['title'],
        'pageTitle' => 'Design invitation',
        'active' => 'invitations',
        'invitation' => $row,
        'blocks' => $parsed['blocks'],
    ]);
}

function action_invitation_save(int $id): void
{
    login_required();
    $row = invitation_row($id);
    if ($row === null) {
        flash('error', 'That invitation was not found.');
        redirect(url('invitations'));
    }
    $title = post_string('title', 120);
    $subject = post_string('subject', 160);
    if ($title === '') {
        flash('error', 'Name the invitation.');
        redirect(url('invitations/' . $id));
    }
    if ($subject === '') {
        $subject = $title;
    }
    $parsed = invitation_blocks_from_json((string) ($_POST['blocks'] ?? ''));
    if ($parsed['error'] !== null) {
        flash('error', $parsed['error']);
        redirect(url('invitations/' . $id));
    }
    $previous = invitation_blocks_from_json((string) $row['blocks_json']);
    $kept = [];
    foreach ($parsed['blocks'] as $block) {
        if ($block['type'] === 'image') {
            $kept[] = $block['token'];
        }
    }
    invitation_drop_images($previous['blocks'], $kept);
    db_exec(
        'UPDATE invitations SET title = ?, subject = ?, blocks_json = ? WHERE id = ?',
        [$title, $subject, json_encode($parsed['blocks'], JSON_UNESCAPED_UNICODE), $id]
    );
    flash('success', 'Invitation saved.');
    redirect(url('invitations/' . $id));
}

function action_invitation_image(int $id): void
{
    login_required();
    header('Content-Type: application/json; charset=utf-8');
    if (invitation_row($id) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'That invitation was not found.']);
        return;
    }
    $file = $_FILES['image'] ?? null;
    $stored = invitation_store_upload(is_array($file) ? $file : []);
    if ($stored['token'] === null) {
        http_response_code(422);
        echo json_encode(['error' => $stored['error']]);
        return;
    }
    echo json_encode([
        'token' => $stored['token'],
        'url' => url('invitations/media/' . $stored['token']),
    ]);
}

function action_invitation_media(string $token): void
{
    login_required();
    $path = invitation_image_path($token);
    if ($path === null) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    header('Content-Type: ' . invitation_image_mime($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
}

function action_invitation_send_page(int $id): void
{
    login_required();
    $row = invitation_row($id);
    if ($row === null) {
        flash('error', 'That invitation was not found.');
        redirect(url('invitations'));
    }
    $parsed = invitation_blocks_from_json((string) $row['blocks_json']);
    render('invitation_send', [
        'title' => 'Send invitation',
        'pageTitle' => 'Send invitation',
        'active' => 'invitations',
        'invitation' => $row,
        'ready' => invitation_has_content($parsed['blocks']),
        'donors' => invitation_email_donors(),
        'mailReady' => smtp_is_ready(load_messaging_settings()),
    ]);
}

function action_invitation_send(int $id): void
{
    login_required();
    $row = invitation_row($id);
    if ($row === null) {
        flash('error', 'That invitation was not found.');
        redirect(url('invitations'));
    }
    $parsed = invitation_blocks_from_json((string) $row['blocks_json']);
    $ids = posted_id_list('donor_ids');
    $settings = load_messaging_settings();
    $error = invitation_send_error(count($ids), smtp_is_ready($settings), $parsed['error'] === null && invitation_has_content($parsed['blocks']));
    if ($parsed['error'] !== null) {
        $error = $parsed['error'];
    }
    if ($error !== null) {
        flash('error', $error);
        redirect(url('invitations/' . $id . '/send'));
    }
    $blocks = $parsed['blocks'];
    $images = invitation_inline_images($blocks);
    $withLogo = brand_logo_email_image() !== null;
    $subject = (string) $row['subject'];
    $sent = 0;
    $skipped = 0;
    $failed = 0;
    foreach ($ids as $donorId) {
        $donor = db_one('SELECT name, email FROM donors WHERE id = ?', [$donorId]);
        $email = trim((string) ($donor['email'] ?? ''));
        if ($donor === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $skipped++;
            continue;
        }
        $name = (string) $donor['name'];
        $sendError = send_smtp_message(
            $settings,
            $email,
            $subject,
            invitation_email_plain($blocks, $name),
            null,
            invitation_email_html($blocks, $name, $withLogo),
            $images
        );
        if ($sendError !== null) {
            notify_log('EMAIL', $email, 'Not sent. ' . $subject . ' ' . $sendError);
            $failed++;
            continue;
        }
        notify_log('EMAIL', $email, 'Sent. ' . $subject);
        $sent++;
    }
    [$category, $text] = bulk_result_flash($sent, $skipped, $failed);
    flash($category, $text);
    redirect(url('invitations/' . $id . '/send'));
}

function action_invitation_delete(int $id): void
{
    login_required();
    $row = invitation_row($id);
    if ($row === null) {
        flash('error', 'That invitation was not found.');
        redirect(url('invitations'));
    }
    $parsed = invitation_blocks_from_json((string) $row['blocks_json']);
    invitation_drop_images($parsed['blocks'], []);
    db_exec('DELETE FROM invitations WHERE id = ?', [$id]);
    flash('success', 'Invitation removed.');
    redirect(url('invitations'));
}
