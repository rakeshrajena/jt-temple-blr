<?php
declare(strict_types=1);

/**
 * Public JSON for home site — same Identity as CRM Settings / Booking / Donation.
 * Light bootstrap (no full CRM actions / schema migrations).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$crmRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'crm');
if ($crmRoot === false || !is_file($crmRoot . '/config.php')) {
    http_response_code(503);
    echo json_encode(['error' => 'crm_unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
}

define('APP_ROOT', $crmRoot);

require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/db.php';
require __DIR__ . '/brand_logo.php';

/**
 * @return array<string, string>
 */
function identity_app_settings(PDO $pdo, array $keys): array
{
  $out = [];
    if ($keys === []) {
        return $out;
    }
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)"
    );
    $stmt->execute($keys);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out[(string) $row['setting_key']] = trim((string) ($row['setting_value'] ?? ''));
    }

    return $out;
}

/** @return array<string, string> */
function identity_booking_settings(PDO $pdo): array
{
    $has = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    if ($has === false) {
        return [];
    }
    $out = [];
    foreach ($pdo->query('SELECT skey, svalue FROM settings') as $row) {
        $out[(string) $row['skey']] = trim((string) ($row['svalue'] ?? ''));
    }

    return $out;
}

function identity_pick(array $app, array $booking, string $appKey, string $bookingKey, string $default = ''): string
{
    $v = trim($app[$appKey] ?? '');
    if ($v !== '') {
        return $v;
    }
    $v = trim($booking[$bookingKey] ?? '');

    return $v !== '' ? $v : $default;
}

try {
    $pdo = db_connect();
    $keys = [
        'brand_name', 'brand_place', 'brand_address', 'brand_phone', 'brand_email',
        'brand_website', 'brand_gstin',
    ];
    $app = identity_app_settings($pdo, $keys);
    $booking = identity_booking_settings($pdo);

    $name = identity_pick($app, $booking, 'brand_name', 'temple_name', APP_NAME);
    $place = identity_pick($app, $booking, 'brand_place', 'temple_place', APP_PLACE);
    if ($place === '') {
        $place = APP_PLACE;
    }
    $address = identity_pick($app, $booking, 'brand_address', 'temple_address');
    $phone = identity_pick($app, $booking, 'brand_phone', 'temple_phone');
    $email = identity_pick($app, $booking, 'brand_email', 'temple_email');
    $website = identity_pick($app, $booking, 'brand_website', 'temple_website');
    $gstin = identity_pick($app, $booking, 'brand_gstin', 'temple_gstin');

    $logoPath = home_api_brand_logo_path(APP_ROOT, $pdo);
    $faviconUrl = home_api_favicon_url();
    $logoUrl = home_api_brand_public_url(APP_ROOT, $logoPath);

    $addressLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $address) ?: [])));
    if ($addressLines === [] && $address !== '') {
        $addressLines = [trim($address)];
    }

    $firstPhone = $phone;
    if (preg_match('/\+?\d[\d\s\-]{8,15}/', $phone, $phoneMatch) === 1) {
        $firstPhone = trim($phoneMatch[0]);
    }
    $phoneDigits = preg_replace('/\D+/', '', $firstPhone) ?? '';
    $tel = $phoneDigits !== '' ? 'tel:+' . ltrim($phoneDigits, '+') : '';

    $shortPlace = trim(explode(',', $place)[0] ?? $place);
    $title = $place !== '' ? $name . ', ' . $place : $name;

    echo json_encode(
        [
            'name' => $name,
            'place' => $place,
            'title' => $title . ' | Puja, Rituals & Online Booking',
            'navTitle' => $shortPlace !== '' ? $name . ' ' . $shortPlace : $name,
            'welcomeTitle' => 'Welcome to ' . ($shortPlace !== '' ? $name . ' ' . $shortPlace : $name),
            'address' => $address,
            'addressLines' => $addressLines,
            'phone' => $phone,
            'phoneDisplay' => $phone !== '' ? 'Phone: ' . $phone : '',
            'tel' => $tel,
            'email' => $email,
            'website' => $website,
            'gstin' => $gstin,
            'faviconUrl' => $faviconUrl,
            'faviconType' => home_api_brand_mime($logoPath),
            'logoUrl' => $logoUrl,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'identity_unavailable'], JSON_UNESCAPED_SLASHES);
}