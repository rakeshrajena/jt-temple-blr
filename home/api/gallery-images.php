<?php
declare(strict_types=1);

/**
 * Home site only: list images in home/gallery/ (newest first).
 * Drop JPG/PNG/WebP files in that folder — no CRM/Booking/Streaming changes.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

$galleryDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gallery');
if ($galleryDir === false || !is_dir($galleryDir)) {
    echo json_encode(['images' => [], 'error' => 'gallery_folder_missing'], JSON_UNESCAPED_SLASHES);
    exit;
}

$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
/** Skip junk / phone screenshots if accidentally copied in. */
$skipName = '/(?:^|[_.-])(phone|screenshot|thumb|mockup)(?:[_.-]|$)/i';

$images = [];

$iterator = new DirectoryIterator($galleryDir);
foreach ($iterator as $entry) {
    if ($entry->isDot() || !$entry->isFile()) {
        continue;
    }
    $filename = $entry->getFilename();
    if (str_starts_with($filename, '.')) {
        continue;
    }
  $ext = strtolower($entry->getExtension());
    if (!in_array($ext, $allowedExt, true)) {
        continue;
    }
    if (preg_match($skipName, $filename) === 1) {
        continue;
    }

    $mtime = $entry->getMTime();
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $alt = ucwords(str_replace(['-', '_'], ' ', $stem));

    $images[] = [
        'src' => 'home/gallery/' . rawurlencode($filename),
        'name' => $stem,
        'alt' => $alt,
        'mtime' => $mtime,
        'modified' => date('c', $mtime),
    ];
}

usort(
    $images,
    static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']
);

echo json_encode(
    [
        'images' => array_map(
            static function (array $row): array {
                unset($row['mtime']);
                return $row;
            },
            $images
        ),
        'count' => count($images),
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);