<?php
declare(strict_types=1);

const CONTRIBUTOR_IMAGE_EXTENSIONS = ['jpg', 'png', 'gif', 'webp'];
const CONTRIBUTOR_IMAGE_MAX_BYTES = 2097152;

function ensure_contributors(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec(
        "CREATE TABLE IF NOT EXISTS contributors (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(120) NOT NULL,
            contact       VARCHAR(30) NOT NULL DEFAULT '',
            email         VARCHAR(120) NOT NULL DEFAULT '',
            location      VARCHAR(120) NOT NULL DEFAULT '',
            designation   VARCHAR(80) NOT NULL DEFAULT '',
            profile_url   VARCHAR(300) NOT NULL DEFAULT '',
            image_file    VARCHAR(40) NULL,
            created_by    INT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB"
    );
    $ready = true;
}

function contributor_input_error(
    string $name,
    string $contact,
    string $email,
    string $location,
    string $designation,
    string $profileUrl
): ?string {
    if ($name === '') {
        return 'Enter the contributor name.';
    }
    if (contributor_has_markup($name) || contributor_has_markup($location) || contributor_has_markup($designation)) {
        return 'Name, location, and designation cannot contain those characters.';
    }
    if ($contact !== '' && preg_match('/^[0-9+().\s-]{1,30}$/', $contact) !== 1) {
        return 'Enter a contact number using digits, spaces, and + - ( ).';
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Enter a valid email address.';
    }
    if ($profileUrl === '') {
        return null;
    }
    if (filter_var($profileUrl, FILTER_VALIDATE_URL) === false) {
        return 'Enter a public profile link that starts with https://.';
    }
    $scheme = strtolower((string) parse_url($profileUrl, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return 'Enter a public profile link that starts with https://.';
    }
    return null;
}

function contributor_has_markup(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F<>]/u', $value) === 1;
}

/**
 * @param mixed $file
 * @return array{extension: string, path: string, uploaded: bool}|array{empty: true}|array{error: string}
 */
function contributor_image_upload(mixed $file): array
{
    if (is_array($file) && ($file['uploaded'] ?? null) === false && isset($file['source']) && is_string($file['source'])) {
        $checked = contributor_image_extension($file['source'], (string) ($file['name'] ?? 'photo.png'));
        if (isset($checked['error'])) {
            return ['error' => $checked['error']];
        }
        return ['extension' => $checked['extension'], 'path' => $file['source'], 'uploaded' => false];
    }
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['empty' => true];
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'The photo must be 2 MB or smaller.'];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['error' => 'The photo upload did not complete.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['error' => 'The photo upload did not complete.'];
    }
    $checked = contributor_image_extension($tmp, (string) ($file['name'] ?? 'photo'));
    if (isset($checked['error'])) {
        return ['error' => $checked['error']];
    }
    return ['extension' => $checked['extension'], 'path' => $tmp, 'uploaded' => true];
}

/**
 * @return array{extension: string}|array{error: string}
 */
function contributor_image_extension(string $path, string $originalName): array
{
    if (!is_file($path)) {
        return ['error' => 'The photo could not be read.'];
    }
    $size = filesize($path);
    if ($size === false || $size < 1) {
        return ['error' => 'The photo file is empty.'];
    }
    if ($size > CONTRIBUTOR_IMAGE_MAX_BYTES) {
        return ['error' => 'The photo must be 2 MB or smaller.'];
    }
    $info = @getimagesize($path);
    if (!is_array($info)) {
        return ['error' => 'Choose a JPG, PNG, GIF, or WebP photo.'];
    }
    $extension = match ((int) ($info[2] ?? 0)) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
    if ($extension === null || !in_array($extension, CONTRIBUTOR_IMAGE_EXTENSIONS, true)) {
        return ['error' => 'Choose a JPG, PNG, GIF, or WebP photo.'];
    }
    $original = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($original === 'php' || $original === 'phtml' || $original === 'svg') {
        return ['error' => 'Choose a JPG, PNG, GIF, or WebP photo.'];
    }
    return ['extension' => $extension];
}

/**
 * @return list<array<string, mixed>>
 */
function contributor_list(): array
{
    ensure_contributors();
    return db_all('SELECT id, name, contact, email, location, designation, profile_url, image_file, created_at FROM contributors ORDER BY id');
}

function save_contributor(?int $id, mixed $file): ?string
{
    ensure_contributors();
    $name = post_string('name', 120);
    $contact = post_string('contact', 30);
    $email = post_string('email', 120);
    $location = post_string('location', 120);
    $designation = post_string('designation', 80);
    $profileUrl = post_string('profile_url', 300);
    $error = contributor_input_error($name, $contact, $email, $location, $designation, $profileUrl);
    if ($error !== null) {
        return $error;
    }
    $image = contributor_image_upload($file);
    if (isset($image['error'])) {
        return $image['error'];
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    $written = null;
    try {
        if ($id === null) {
            $id = db_exec(
                'INSERT INTO contributors (name, contact, email, location, designation, profile_url, created_by) VALUES (?,?,?,?,?,?,?)',
                [$name, $contact, $email, $location, $designation, $profileUrl, (int) ($_SESSION['user_id'] ?? 0) ?: null]
            );
        } else {
            $existing = db_one('SELECT id, image_file FROM contributors WHERE id = ? FOR UPDATE', [$id]);
            if ($existing === null) {
                if ($own && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return 'That contributor was not found.';
            }
            db_exec(
                'UPDATE contributors SET name = ?, contact = ?, email = ?, location = ?, designation = ?, profile_url = ? WHERE id = ?',
                [$name, $contact, $email, $location, $designation, $profileUrl, $id]
            );
        }
        if (!isset($image['empty'])) {
            $written = contributor_write_image($id, $image['path'], $image['extension'], $image['uploaded']);
            db_exec('UPDATE contributors SET image_file = ? WHERE id = ?', [basename($written), $id]);
            contributor_remove_other_images($id, basename($written));
        }
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($written !== null && is_file($written)) {
            unlink($written);
        }
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function contributor_write_image(int $id, string $source, string $extension, bool $uploaded): string
{
    $dir = APP_ROOT . '/storage/contributors';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not store the photo.');
    }
    $dest = $dir . DIRECTORY_SEPARATOR . $id . '.' . $extension;
    $stored = $uploaded ? move_uploaded_file($source, $dest) : copy($source, $dest);
    if ($stored !== true) {
        throw new RuntimeException('Could not store the photo.');
    }
    return $dest;
}

function contributor_remove_other_images(int $id, string $keep): void
{
    $dir = APP_ROOT . '/storage/contributors';
    foreach (glob($dir . DIRECTORY_SEPARATOR . $id . '.*') ?: [] as $path) {
        if (is_file($path) && basename($path) !== $keep) {
            unlink($path);
        }
    }
}

function delete_contributor(int $id): bool
{
    ensure_contributors();
    $row = db_one('SELECT id, image_file FROM contributors WHERE id = ?', [$id]);
    if ($row === null) {
        return false;
    }
    db_exec('DELETE FROM contributors WHERE id = ?', [$id]);
    if ((string) ($row['image_file'] ?? '') !== '') {
        contributor_remove_other_images($id, '');
    }
    return true;
}

function contributor_photo_path(int $id): ?string
{
    ensure_contributors();
    $row = db_one('SELECT image_file FROM contributors WHERE id = ?', [$id]);
    $file = basename((string) ($row['image_file'] ?? ''));
    if (preg_match('/^' . $id . '\.(jpg|png|gif|webp)$/', $file) !== 1) {
        return null;
    }
    $dir = APP_ROOT . '/storage/contributors';
    $candidate = $dir . DIRECTORY_SEPARATOR . $file;
    $real = realpath($candidate);
    $root = realpath($dir);
    if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $real;
}

function serve_contributor_photo(int $id): void
{
    $path = contributor_photo_path($id);
    if ($path === null) {
        http_response_code(404);
        echo 'Photo not found.';
        return;
    }
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => '',
    };
    if ($mime === '') {
        http_response_code(404);
        echo 'Photo not found.';
        return;
    }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
}
