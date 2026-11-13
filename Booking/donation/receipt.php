<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/receipt_service.php';

use SJT\Core\Request;

$req = Request::fromGlobals();
$token = trim((string) ($req->query['t'] ?? ''));
$attachment = ($req->query['download'] ?? '') === '1';
donation_serve_receipt_pdf_by_token($token, $attachment);
