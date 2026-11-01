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

check(contributor_input_error('', '', '', '', '', '') === 'Enter the contributor name.', 'name is required');
check(contributor_input_error('Ada', '123', 'not-an-email', '', 'Builder', '') !== null, 'email must be valid');
check(contributor_input_error('Ada', 'call me', '', '', '', '') !== null, 'contact stays a phone number');
check(contributor_input_error('Ada', '', '', '', '', 'javascript:alert(1)') !== null, 'profile link rejects a script');
check(contributor_input_error('Ada', '+91 98765 43210', 'ada@example.org', 'Bengaluru', 'Builder', 'https://example.org/ada') === null, 'a full card is accepted');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
$pngPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_contributor.png';
$textPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_contributor.txt';
file_put_contents($pngPath, $png);
file_put_contents($textPath, 'not a photo');
$pngType = contributor_image_extension($pngPath, 'face.png');
$textType = contributor_image_extension($textPath, 'face.png');
check(($pngType['extension'] ?? '') === 'png', 'png photo is accepted');
check(isset($textType['error']), 'a text file is not a photo');

ensure_contributors();
$pdo = db();
$pdo->beginTransaction();
$written = null;
try {
    $_POST = [
        'name' => 'Test Contributor',
        'contact' => '+91 90000 00000',
        'email' => 'test.contributor@example.org',
        'location' => 'Sarjapura',
        'designation' => 'Advisor',
        'profile_url' => 'https://example.org/test',
    ];
    $error = save_contributor(null, ['uploaded' => false, 'source' => $pngPath, 'name' => 'face.png']);
    check($error === null, 'contributor is saved');
    $row = db_one('SELECT id, image_file, designation FROM contributors WHERE name = ?', ['Test Contributor']);
    check($row !== null && $row['designation'] === 'Advisor', 'saved row keeps the designation');
    $written = $row === null ? null : (APP_ROOT . '/storage/contributors/' . $row['image_file']);
    check($written !== null && is_file($written), 'photo is stored beside the card');
    if ($row !== null) {
        $_POST['name'] = 'Test Contributor Updated';
        $_POST['designation'] = 'Guide';
        $updated = save_contributor((int) $row['id'], null);
        $after = db_one('SELECT name, designation, image_file FROM contributors WHERE id = ?', [(int) $row['id']]);
        check($updated === null && $after !== null && $after['name'] === 'Test Contributor Updated' && $after['designation'] === 'Guide' && $after['image_file'] === $row['image_file'], 'update keeps the photo and changes the name');
        check(delete_contributor((int) $row['id']) === true, 'contributor can be deleted');
        check(db_one('SELECT id FROM contributors WHERE id = ?', [(int) $row['id']]) === null, 'deleted contributor is gone');
        check(!is_file((string) $written), 'deleted photo is removed');
        $written = null;
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_string($written) && is_file($written)) {
        unlink($written);
    }
    @unlink($pngPath);
    @unlink($textPath);
    $_POST = [];
}

check(db_one('SELECT id FROM contributors WHERE name = ?', ['Test Contributor']) === null, 'the test contributor is not left in the books');

echo $failed === 0 ? "all contributor tests passed\n" : "{$failed} failed\n";
exit($failed === 0 ? 0 : 1);
