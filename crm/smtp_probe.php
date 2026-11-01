<?php
declare(strict_types=1);

/** Temporary SMTP diagnostic. Admin login required. Delete after fixing mail. */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['Admin', 'Treasurer'], true)) {
    http_response_code(403);
    exit('Sign in to ERP as Admin or Treasurer.');
}

$settings = load_messaging_settings();
$to = trim((string) ($_POST['test_to'] ?? ''));
if ($to === '' && str_contains((string) ($user['username'] ?? ''), '@')) {
    $to = (string) $user['username'];
}

function smtp_probe_connect(string $remote, int $timeout = 8): array
{
    $errno = 0;
    $errstr = '';
    $t0 = microtime(true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        str_starts_with($remote, 'ssl://') ? $ctx : null,
    );
    $ms = (int) round((microtime(true) - $t0) * 1000);
    if ($socket === false) {
        return ['ok' => false, 'ms' => $ms, 'errno' => $errno, 'errstr' => $errstr, 'greeting' => ''];
    }
    $greeting = fgets($socket, 512) ?: '';
    fclose($socket);
    return ['ok' => str_starts_with($greeting, '220'), 'ms' => $ms, 'errno' => 0, 'errstr' => '', 'greeting' => trim($greeting)];
}

$sendResult = null;
$sendOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    require_csrf();
    $email = trim((string) ($_POST['test_to'] ?? ''));
    $to = $email;
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $sendResult = 'Enter a valid test email address in the To field, then click Send test again.';
    } elseif (!smtp_is_ready($settings)) {
        $sendResult = 'SMTP is not configured in Settings › Messages (need host + From email).';
    } else {
        $err = send_smtp_message(
            $settings,
            $email,
            'Temple SMTP test',
            "This is a test from smtp_probe.php on " . date('c') . ".\n\nIf you received this, booking emails should work.",
        );
        if ($err === null) {
            $sendOk = true;
            $sendResult = 'OK — Exim accepted the message for ' . $email . '. Check inbox and spam in 2–5 minutes, then retry failed booking emails in Booking admin.';
        } else {
            $sendResult = $err;
        }
    }
}
$localOnly = env_value('SMTP_ONLY_LOCAL_MTA', '') === '1' || smtp_prefer_local_mta_first();

$enc = $settings['smtp_encryption'] ?? 'tls';
$host = trim($settings['smtp_host'] ?? '');
$port = (int) ($settings['smtp_port'] ?? 587);
$primary = $host !== '' ? (($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port) : '(not set)';
$probes = [
    'ERP Settings (primary)' => ['remote' => $primary, 'timeout' => 8],
    'GoDaddy relay' => ['remote' => 'tcp://relay-hosting.secureserver.net:25', 'timeout' => 8],
    'Local MTA (127.0.0.1)' => ['remote' => 'tcp://127.0.0.1:25', 'timeout' => 5],
    'Local MTA (localhost)' => ['remote' => 'tcp://localhost:25', 'timeout' => 5],
];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>SMTP probe</title>
<style>body{font-family:system-ui;max-width:760px;margin:24px auto;padding:0 16px;line-height:1.45}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:8px;font-size:14px}.ok{color:green}.bad{color:#b00020}.warn{background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:8px;margin:16px 0}code{font-size:13px}</style>
</head><body>
<h1>SMTP probe</h1>
<p>Delete <code>smtp_probe.php</code> after mail works. Booking + ERP use <code>app_settings</code> (Settings › Messages).</p>
<ul>
  <li>Host: <code><?= e($host ?: '—') ?></code> · Port: <?= (int) $port ?> · Encryption: <?= e($enc) ?></li>
  <li>From: <?= e($settings['smtp_from_email'] ?? '—') ?></li>
  <li><code>sendmail_path</code>: <code><?= e((string) ini_get('sendmail_path')) ?: '(empty)' ?></code></li>
  <li>PHP <code>mail()</code>: <?= function_exists('mail') ? 'available' : '<span class="bad">disabled</span>' ?></li>
  <li>Send mode: <?= $localOnly ? '<span class="ok">local Exim first / only</span> (SMTP_ONLY_LOCAL_MTA or GoDaddy host)' : 'remote SMTP then local fallback' ?></li>
</ul>
<?php if (smtp_prefer_local_mta_first()): ?>
<div class="ok" style="padding:12px;border:1px solid #badbcc;background:#d1e7dd;border-radius:8px;margin:16px 0">
  <strong>Local Exim is available.</strong> On this server the app sends through <code>127.0.0.1:25</code> first (no smtpout login).
  Keep <strong>From</strong> as <code>support@sjtbengaluru.org</code> (must be a real mailbox on this account). Use <strong>Send test</strong> below, then retry failed booking emails.
</div>
<?php else: ?>
<div class="warn">
  <strong>errno 110</strong> on remote SMTP is normal on GoDaddy; use local MTA when it connects (upload latest <code>settings.php</code> + <code>SmtpMail.php</code>).
</div>
<?php endif; ?>
<h2>TCP connect test</h2>
<table>
<tr><th>Route</th><th>Target</th><th>Result</th></tr>
<?php foreach ($probes as $label => $spec): ?>
<?php
  $remote = $spec['remote'];
  if ($remote === '(not set)') {
      $r = ['ok' => false, 'errstr' => 'Configure SMTP host first', 'errno' => 0, 'ms' => 0, 'greeting' => ''];
  } else {
      $r = smtp_probe_connect($remote, (int) $spec['timeout']);
  }
?>
<tr>
  <td><?= e($label) ?></td>
  <td><code><?= e($remote) ?></code></td>
  <td class="<?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? 'Connected (' . $r['ms'] . ' ms) ' . e($r['greeting']) : 'Failed errno ' . $r['errno'] . ' ' . e($r['errstr']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<h2>Send test (local Exim — same as booking emails)</h2>
<?php if (is_string($sendResult)): ?>
<p class="<?= $sendOk ? 'ok' : 'bad' ?>" style="padding:12px;border-radius:8px;border:1px solid <?= $sendOk ? '#badbcc' : '#f5c2c7' ?>;background:<?= $sendOk ? '#d1e7dd' : '#f8d7da' ?>"><?= e($sendResult) ?></p>
<?php else: ?>
<p class="muted">Enter your Gmail below and click <strong>Send test</strong>. A green OK line should appear on this page.</p>
<?php endif; ?>
<form method="post"><?= csrf_field() ?>
<p><label>To (your email) <input type="email" name="test_to" value="<?= e($to) ?>" required style="min-width:280px" placeholder="you@gmail.com"></label></p>
<button type="submit" name="send_test" value="1">Send test</button>
</form>
<p><a href="<?= e(url('settings') . '#messages') ?>">← Settings › Messages</a></p>
</body></html>
