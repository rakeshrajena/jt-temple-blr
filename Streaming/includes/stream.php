<?php
declare(strict_types=1);

const STREAMING_FILE = __DIR__ . '/../.streaming';

/** @return list<string> */
function streaming_unavailable_lines(): array
{
    return [
        'Live stream darshan is not available at the moment.',
        'लाइव स्ट्रीम दर्शन इस समय उपलब्ध नहीं है।',
        'ଏହି ସମୟରେ ଲାଇଭ ଷ୍ଟ୍ରିମ ଦର୍ଶନ ଉପଲବ୍ଧ ନାହିଁ।',
        'ಈ ಸಮಯದಲ್ಲಿ ಲೈವ್ ಸ್ಟ್ರೀಮ್ ದರ್ಶನ ಲಭ್ಯವಿಲ್ಲ.',
    ];
}

function streaming_normalize_url(string $raw): ?string
{
    $line = trim($raw);
    if ($line === '') {
        return null;
    }
    if (filter_var($line, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) (parse_url($line, PHP_URL_SCHEME) ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    return $line;
}

function streaming_read_url(): ?string
{
    if (!is_file(STREAMING_FILE) || !is_readable(STREAMING_FILE)) {
        return null;
    }
    $raw = file_get_contents(STREAMING_FILE);
    if ($raw === false) {
        return null;
    }
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        return streaming_normalize_url($line);
    }
    return null;
}

/** @return array{ok:bool,error?:string} */
function streaming_write_url(string $rawUrl): array
{
    $url = streaming_normalize_url($rawUrl);
    if ($url === null) {
        return ['ok' => false, 'error' => 'Enter a valid http or https stream URL (for example a YouTube live link).'];
    }
    $dir = dirname(STREAMING_FILE);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'error' => 'The Streaming folder is not writable on the server.'];
    }
    $body = $url . "\n";
    $tmp = STREAMING_FILE . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $body, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Could not save the stream URL.'];
    }
    if (!rename($tmp, STREAMING_FILE)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Could not update .streaming on the server.'];
    }
    return ['ok' => true];
}

function streaming_embed_src(string $url): string
{
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/|youtube\.com/live/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?'
            . http_build_query([
                'rel' => '0',
                'autoplay' => '1',
                'mute' => '1',
                'playsinline' => '1',
            ]);
    }
    if (preg_match('~facebook\.com/.+/videos/(\d+)~', $url, $m)) {
        return 'https://www.facebook.com/plugins/video.php?'
            . http_build_query([
                'href' => $url,
                'show_text' => 'false',
                'autoplay' => 'true',
            ]);
    }
    return $url;
}

function streaming_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
