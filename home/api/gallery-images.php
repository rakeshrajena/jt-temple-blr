<?php
declare(strict_types=1);

/**
 * Home site only: images under home/gallery/ and subfolders (each folder = tab).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

$galleryDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gallery');
if ($galleryDir === false || !is_dir($galleryDir)) {
    echo json_encode(['images' => [], 'tabs' => [], 'error' => 'gallery_folder_missing'], JSON_UNESCAPED_SLASHES);
    exit;
}

$allowedExt = [
    'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'svg', 'ico', 'tif', 'tiff', 'heic', 'heif',
];
$skipName = '/(?:^|[_.-])(phone|screenshot|thumb|mockup)(?:[_.-]|$)/i';

/**
 * @return list<array{src:string,name:string,alt:string,mtime:int,folder:string}>
 */
function gallery_collect_files(string $dir, string $urlPrefix, string $folderId): array
{
    global $allowedExt, $skipName;
    $out = [];
    $iterator = new DirectoryIterator($dir);
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
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $rel = $urlPrefix . rawurlencode($filename);
        $out[] = [
            'src' => 'home/gallery/' . $rel,
            'name' => $stem,
            'alt' => ucwords(str_replace(['-', '_'], ' ', $stem)),
            'mtime' => $entry->getMTime(),
            'folder' => $folderId,
        ];
    }
    usort($out, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

    return $out;
}

$byFolder = [];
$rootImages = gallery_collect_files($galleryDir, '', 'root');
if ($rootImages !== []) {
    $byFolder['root'] = $rootImages;
}

/** @var array<string, string> */
$folderNames = [];

foreach (new DirectoryIterator($galleryDir) as $entry) {
    if ($entry->isDot() || !$entry->isDir()) {
        continue;
    }
    $folderName = $entry->getFilename();
    if (str_starts_with($folderName, '.')) {
        continue;
    }
    $folderId = preg_replace('/[^a-z0-9_-]+/i', '-', $folderName) ?? $folderName;
    $folderNames[$folderId] = $folderName;
    $prefix = rawurlencode($folderName) . '/';
    $imgs = gallery_collect_files($entry->getPathname(), $prefix, $folderId);
    if ($imgs !== []) {
        $byFolder[$folderId] = $imgs;
    }
}

$allImages = [];
foreach ($byFolder as $list) {
    foreach ($list as $img) {
        $allImages[] = $img;
    }
}
usort($allImages, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

$strip = static function (array $row): array {
    unset($row['mtime']);

    return $row;
};

/** Tabs only when at least one subfolder has images (empty folders are ignored). */
$folderTabs = [];
foreach ($byFolder as $folderId => $list) {
    if ($folderId === 'root') {
        continue;
    }
    $name = $folderNames[$folderId] ?? $folderId;
    $folderTabs[] = [
        'id' => $folderId,
        'label' => ucwords(str_replace(['-', '_'], ' ', $name)),
        'images' => array_map($strip, $list),
    ];
}

usort(
    $folderTabs,
    static fn(array $a, array $b): int => strcmp($a['label'], $b['label'])
);

$tabs = [];
if ($folderTabs !== []) {
    $tabs[] = [
        'id' => 'all',
        'label' => 'All',
        'images' => array_map($strip, $allImages),
    ];
    if (isset($byFolder['root']) && $byFolder['root'] !== []) {
        $tabs[] = [
            'id' => 'root',
            'label' => 'General',
            'images' => array_map($strip, $byFolder['root']),
        ];
    }
    foreach ($folderTabs as $folderTab) {
        $tabs[] = $folderTab;
    }
}

echo json_encode(
    [
        'images' => array_map($strip, $allImages),
        'tabs' => $tabs,
        'count' => count($allImages),
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);