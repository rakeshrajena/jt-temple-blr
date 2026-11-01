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

check(messaging_settings_error([
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_host' => '',
    'smtp_from_email' => '',
    'smtp_username' => '',
    'whatsapp_country_code' => '91',
    'whatsapp_template' => 'Namaskar {name}',
]) === null, 'WhatsApp settings can be saved before a mail server is entered');
check(messaging_settings_error([
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_host' => 'smtp.example.com',
    'smtp_from_email' => '',
    'whatsapp_country_code' => '91',
    'whatsapp_template' => 'Hello',
]) !== null, 'a mail server needs a From address');
check(messaging_settings_error([
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_host' => '',
    'smtp_from_email' => '',
    'whatsapp_country_code' => '',
    'whatsapp_template' => 'Hello',
]) !== null, 'the country code is required');
check(whatsapp_phone_digits('9876543210', '91') === '919876543210', 'a 10-digit mobile gets the country code');
check(whatsapp_phone_digits('+91 98765 43210', '91') === '919876543210', 'an existing country code is not added twice');
$url = whatsapp_web_url('9876543210', 'Namaskar Meera', '91');
check(
    $url === 'https://web.whatsapp.com/send?phone=919876543210&text=Namaskar%20Meera',
    'WhatsApp Web is a browser link, not an API call'
);
check(
    fill_message_template('Namaskar {name}, Rs.{amount}', ['name' => 'Meera', 'amount' => '500']) === 'Namaskar Meera, Rs.500',
    'the message fills the devotee and the amount'
);
check(smtp_is_ready(messaging_defaults()) === false, 'mail is not ready until a server and From address are saved');
check(smtp_dot_stuff("Hello\n.secret") === "Hello\r\n..secret", 'a leading dot in the message is escaped for SMTP');

$admin = db_one("SELECT id FROM users WHERE username = 'admin'");
check($admin !== null, 'demo admin exists');

$pdo = db();
$pdo->beginTransaction();
try {
    $saved = save_messaging_settings([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => 'temple',
        'smtp_password' => 'secret-mail',
        'smtp_from_email' => 'seva@temple.test',
        'smtp_from_name' => 'Temple',
        'whatsapp_country_code' => '91',
        'whatsapp_template' => 'Namaskar {name}',
        'clear_smtp_password' => '',
    ], messaging_defaults());
    $loaded = load_messaging_settings();
    $kept = save_messaging_settings([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => 'temple',
        'smtp_password' => '',
        'smtp_from_email' => 'seva@temple.test',
        'smtp_from_name' => 'Temple',
        'whatsapp_country_code' => '91',
        'whatsapp_template' => 'Namaskar {name}',
        'clear_smtp_password' => '',
    ], $loaded);
    $again = load_messaging_settings();
    check(
        $saved === null
        && $kept === null
        && $again['smtp_password'] === 'secret-mail'
        && smtp_is_ready($again)
        && $again['whatsapp_template'] === 'Namaskar {name}',
        'a blank password keeps the saved one, and a complete server is ready to send'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all settings tests passed\n";
