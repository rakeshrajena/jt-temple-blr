<?php
declare(strict_types=1);

namespace SJT\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
        public array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = App::basePath();
        if ($base !== '' && stripos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $path = '/' . trim(rawurldecode($uri), '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** Trimmed string input; arrays are rejected (returns ''). */
    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return (is_string($v) && preg_match('/^-?\d{1,9}$/', trim($v))) ? (int) trim($v) : $default;
    }

    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : [];
    }

    public function param(string $key): string
    {
        return (string) ($this->params[$key] ?? '');
    }

    public function ip(): string
    {
        $h = Env::get('TRUSTED_IP_HEADER');
        if ($h) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $h));
            $v = trim(explode(',', (string) ($this->server[$key] ?? ''))[0]);
            if (filter_var($v, FILTER_VALIDATE_IP)) {
                return $v;
            }
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['SERVER_PORT'] ?? '') === '443')
            || str_starts_with((string) Env::get('APP_URL', ''), 'https://');
    }

    public function header(string $name): string
    {
        return (string) ($this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '');
    }

    public function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }
}
