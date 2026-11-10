<?php
declare(strict_types=1);

use SJT\Core\Request;

/**
 * @return array{type: string, ref?: string, download?: bool}
 */
function donation_parse_route(Request $req): array
{
    $path = $req->path;
    if (str_starts_with($path, '/donation')) {
        $path = substr($path, strlen('/donation'));
    }
    $path = trim($path, '/');

    if ($path === '' || $path === 'index.php') {
        return ['type' => 'form'];
    }

    $paidLegacy = trim((string) ($req->query['paid'] ?? ''));
    if ($paidLegacy !== '' && preg_match('/^ODN-/i', $paidLegacy) === 1) {
        return ['type' => 'thanks', 'ref' => $paidLegacy];
    }

    if (preg_match('#^d/([A-Za-z0-9-]+)(/receipt\.pdf)?$#', $path, $m) === 1) {
        return [
            'type' => str_ends_with($path, '/receipt.pdf') ? 'receipt_pdf' : 'thanks',
            'ref' => $m[1],
            'download' => str_ends_with($path, '/receipt.pdf'),
        ];
    }

    return ['type' => 'form'];
}

function donation_ref_url(string $refNo): string
{
    return donation_url('d/' . rawurlencode($refNo));
}

function donation_receipt_pdf_route(string $refNo, bool $download = false): string
{
    $u = donation_url('d/' . rawurlencode($refNo) . '/receipt.pdf');

    return $download ? $u . (str_contains($u, '?') ? '&' : '?') . 'download=1' : $u;
}
