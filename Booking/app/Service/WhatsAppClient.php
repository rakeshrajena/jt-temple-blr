<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Env;

/**
 * WhatsApp Business Platform (Cloud API) client.
 * Credentials come only from .env and are never sent to the browser.
 */
final class WhatsAppClient
{
    public function configured(): bool
    {
        return (bool) (Env::get('WA_ACCESS_TOKEN') && Env::get('WA_PHONE_NUMBER_ID'));
    }

    /**
     * Send an approved template message.
     * @param string[] $bodyParams values for {{1}}, {{2}}, ... in the template body
     * @return array{ok:bool, id:?string, error:?string, retryable:bool}
     */
    public function sendTemplate(string $to, string $template, string $language, array $bodyParams): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'id' => null, 'error' => 'WhatsApp API credentials are not configured (.env WA_ACCESS_TOKEN / WA_PHONE_NUMBER_ID).', 'retryable' => false];
        }
        $base = rtrim(Env::get('WA_API_BASE', 'https://graph.facebook.com'), '/');
        $endpoint = sprintf('%s/%s/%s/messages', $base, Env::get('WA_API_VERSION', 'v21.0'), rawurlencode((string) Env::get('WA_PHONE_NUMBER_ID')));
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => self::clean((string) $v)], array_values($bodyParams)),
                ]],
            ],
        ];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Env::get('WA_ACCESS_TOKEN'), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'id' => null, 'error' => 'Network error: ' . $curlErr, 'retryable' => true];
        }
        $json = json_decode((string) $body, true);
        $id = $json['messages'][0]['id'] ?? null;
        if ($code >= 200 && $code < 300 && $id) {
            return ['ok' => true, 'id' => (string) $id, 'error' => null, 'retryable' => false];
        }
        $msg = $json['error']['message'] ?? ('HTTP ' . $code);
        $detail = $json['error']['error_data']['details'] ?? '';
        // 4xx (bad template / bad number / auth) will not fix itself; 429/5xx may.
        $retryable = $code === 429 || $code >= 500 || $code === 0;
        return ['ok' => false, 'id' => null, 'error' => trim("HTTP $code: $msg $detail"), 'retryable' => $retryable];
    }

    /** Template parameters may not contain new lines, tabs or more than 4 consecutive spaces. */
    public static function clean(string $v): string
    {
        $v = preg_replace('/[\r\n\t]+/', ' ', $v);
        $v = preg_replace('/ {2,}/', ' ', (string) $v);
        $v = trim((string) $v);
        return $v === '' ? '-' : mb_substr($v, 0, 900);
    }
}
