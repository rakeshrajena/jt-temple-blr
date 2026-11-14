<?php
declare(strict_types=1);

/**
 * Home site only: hero slideshow media from home/photos/hero/
 * Images: common web formats. Videos: mp4 and webm only.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

$heroDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . 'hero');
if ($heroDir === false || !is_dir($heroDir)) {
    echo json_encode(['items' => [], 'error' => 'hero_folder_missing'], JSON_UNESCAPED_SLASHES);
    exit;
}

$imageExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'svg'];
$videoExt = ['mp4', 'webm'];
$skipName = '/(?:^|[_.-])(phone|screenshot|thumb|mockup)(?:[_.-]|$)/i';

$items = [];

$iterator = new DirectoryIterator($heroDir);
foreach ($iterator as $entry) {
    if ($entry->isDot() || !$entry->isFile()) {
        continue;
    }
    $filename = $entry->getFilename();
    if (str_starts_with($filename, '.')) {
        continue;
    }
    $ext = strtolower($entry->getExtension());
    $type = null;
    if (in_array($ext, $imageExt, true)) {
        $type = 'image';
    } elseif (in_array($ext, $videoExt, true)) {
        $type = 'video';
    } else {
        continue;
    }
    if (preg_match($skipName, $filename) === 1) {
        continue;
    }

    $items[] = [
        'src' => 'home/photos/hero/' . rawurlencode($filename),
        'name' => pathinfo($filename, PATHINFO_FILENAME),
        'type' => $type,
        'ext' => $ext,
        'mtime' => $entry->getMTime(),
        'modified' => date('c', $entry->getMTime()),
    ];
}

usort(
    $items,
    static function (array $a, array $b): int {
        $ta = $a['type'] === 'video' ? 1 : 0;
        $tb = $b['type'] === 'video' ? 1 : 0;
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
        return strcasecmp($a['name'], $b['name']);
    }
);

echo json_encode(
    [
        'items' => array_map(
            static function (array $row): array {
                unset($row['mtime']);
                return $row;
            },
            $items
        ),
        'count' => count($items),
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);