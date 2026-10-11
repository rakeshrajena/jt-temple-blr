<?php
declare(strict_types=1);

namespace SJT\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /** Redirect to an app path ("/b/..."), or a full URL when $absolute. */
    public static function redirect(string $to, bool $absolute = false): self
    {
        return new self('', 303, ['Location' => $absolute ? $to : url($to)]);
    }

    public static function file(string $content, string $mime, string $filename, bool $inline = false): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        echo $this->body;
    }
}
