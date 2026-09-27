<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

check(t('nav.invitations') === 'Invitations', 'the menu names Invitations');
check(invitation_safe_url('javascript:alert(1)') === '', 'a video link cannot be a script');
check(invitation_safe_url('https://example.com/watch') === 'https://example.com/watch', 'an https video link is kept');
$clean = invitation_clean_text('<strong onclick="alert(1)">Hello</strong><script>bad()</script><img src=x onerror=alert(1)>');
check(
    str_contains($clean, '<strong>Hello</strong>')
    && !str_contains($clean, '<script')
    && !str_contains($clean, '<img')
    && !str_contains($clean, 'onclick'),
    'invitation words keep bold and drop scripts'
);
$bad = invitation_blocks_from_json(json_encode([
    ['type' => 'video', 'url' => 'javascript:alert(1)', 'label' => 'Watch'],
], JSON_THROW_ON_ERROR));
check($bad['error'] !== null && $bad['blocks'] === [], 'a script video is refused');
$video = invitation_blocks_from_json(json_encode([
    ['type' => 'text', 'html' => '<b>Welcome</b>', 'align' => 'center', 'kind' => 'heading'],
    ['type' => 'video', 'url' => 'https://example.com/watch', 'label' => 'Watch the seva'],
], JSON_THROW_ON_ERROR));
check($video['error'] === null && count($video['blocks']) === 2, 'a heading and a video link are saved');
$html = invitation_email_html($video['blocks'], 'R Jena', false);
check(
    str_contains($html, 'Namaskar R Jena')
    && str_contains($html, '<strong>Welcome</strong>')
    && str_contains($html, 'href="https://example.com/watch"')
    && str_contains($html, 'Watch the seva')
    && !str_contains($html, '<iframe'),
    'the email is the designed card with a video link'
);
check(invitation_send_error(0, true, true) !== null, 'send needs a selected devotee');
check(invitation_send_error(101, true, true) !== null, 'send stops after 100 devotees');
check(invitation_send_error(2, false, true) !== null, 'send waits until mail is configured');
check(invitation_send_error(2, true, false) !== null, 'send needs a designed invitation');
check(invitation_send_error(2, true, true) === null, 'a designed invitation to two devotees can be sent');

$token = invitation_store_image_file(APP_ROOT . '/static/namaste.png');
check($token !== null && invitation_image_path((string) $token) !== null, 'a picture can be added to an invitation');
$withImage = $token === null ? ['blocks' => [], 'error' => 'missing'] : invitation_blocks_from_json(json_encode([
    ['type' => 'image', 'token' => $token, 'alt' => 'Temple'],
], JSON_THROW_ON_ERROR));
$imageHtml = invitation_email_html($withImage['blocks'], 'Devotee', false);
$payload = smtp_data_payload(
    'Temple',
    'seva@temple.test',
    'devotee@example.com',
    'Invitation',
    invitation_email_plain($withImage['blocks'], 'Devotee'),
    null,
    $imageHtml,
    invitation_inline_images($withImage['blocks'])
);
check(
    $withImage['error'] === null
    && str_contains($imageHtml, 'cid:inv' . $token)
    && str_contains($payload, 'Content-ID: <inv' . $token . '>')
    && str_contains($payload, 'Namaskar Devotee'),
    'the invitation picture is part of the email'
);
if ($token !== null) {
    invitation_delete_image($token);
}
check(invitation_image_path((string) $token) === null, 'a removed picture leaves the invitation folder');

$pdo = db();
$pdo->beginTransaction();
try {
    db_exec(
        'INSERT INTO donors (name, email) VALUES (?,?)',
        ['Invite Has Mail', 'invite-check@example.com']
    );
    db_exec(
        'INSERT INTO donors (name, email) VALUES (?,?)',
        ['Invite No Mail', '']
    );
    $listed = invitation_email_donors();
    $names = array_column($listed, 'name');
    $emails = array_column($listed, 'email');
    check(in_array('Invite Has Mail', $names, true), 'the send list shows a devotee who has an email');
    check(!in_array('Invite No Mail', $names, true), 'the send list hides a devotee without an email');
    check(in_array('invite-check@example.com', $emails, true), 'the send list shows the email address');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all invitation tests passed\n";
