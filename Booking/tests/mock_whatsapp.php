<?php
// Minimal stand-in for the WhatsApp Cloud API used by the automated tests.
// Numbers ending in 0000 get a permanent 400 error, 5555 a temporary 503.
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$log = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/mock_wa.log';
file_put_contents($log, json_encode(['uri' => $_SERVER['REQUEST_URI'], 'auth' => $auth !== '', 'body' => $body]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
if ($auth !== 'Bearer test-token') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Invalid OAuth access token']]);
    return;
}
$to = (string) ($body['to'] ?? '');
if (str_ends_with($to, '0000')) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Recipient phone number not in allowed list', 'error_data' => ['details' => 'test failure']]]);
    return;
}
if (str_ends_with($to, '5555')) {
    http_response_code(503);
    echo json_encode(['error' => ['message' => 'Service temporarily unavailable']]);
    return;
}
echo json_encode(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => $to]], 'messages' => [['id' => 'wamid.TEST' . bin2hex(random_bytes(6))]]]);
