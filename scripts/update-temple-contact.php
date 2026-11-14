<?php
declare(strict_types=1);

/**
 * CLI: temple contact into CRM app_settings (+ Booking settings when installed).
 * Usage: php scripts/update-temple-contact.php
 */

define('APP_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'crm');

require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/db.php';

$pdo = db_connect();

$address = implode("\n", [
    'Bikkanahalli Village',
    'Sarjapura, Bengaluru 562125',
    'India',
]);
$phone = '+91 89040 32808, +91 9902272002';
$email = 'info@shreejagannathtemplesarjapura.org';
$website = 'https://sjtbengaluru.org';

$upsert = static function (PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
};

$pairs = [
    'brand_address' => $address,
    'brand_phone' => $phone,
    'brand_email' => $email,
];
$existingWebsite = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
$existingWebsite->execute(['brand_website']);
$currentWeb = trim((string) ($existingWebsite->fetchColumn() ?: ''));
if ($currentWeb === '') {
    $pairs['brand_website'] = $website;
}

foreach ($pairs as $key => $value) {
    $upsert($pdo, $key, $value);
}

$hasSettings = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
if ($hasSettings !== false) {
    $booking = [
        'temple_address' => $address,
        'temple_phone' => $phone,
        'temple_email' => $email,
    ];
    if ($currentWeb === '') {
        $booking['temple_website'] = $website;
    }
    $actor = 'update-temple-contact-cli';
    $now = date('Y-m-d H:i:s');
    foreach ($booking as $key => $value) {
        $exists = $pdo->prepare('SELECT 1 FROM settings WHERE skey = ?');
        $exists->execute([$key]);
        if ($exists->fetchColumn()) {
            $pdo->prepare('UPDATE settings SET svalue = ?, updated_at = ?, updated_by = ? WHERE skey = ?')
                ->execute([$value, $now, $actor, $key]);
        } else {
            $pdo->prepare('INSERT INTO settings (skey, svalue, updated_at, updated_by) VALUES (?,?,?,?)')
                ->execute([$key, $value, $now, $actor]);
        }
    }
}

echo "Updated temple contact in app_settings (and Booking settings if present).\n";
echo "Address: " . str_replace("\n", ' | ', $address) . "\n";
echo "Phone: {$phone}\n";
echo "Email: {$email}\n";