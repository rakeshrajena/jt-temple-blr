<?php
declare(strict_types=1);

namespace SJT\Core;

final class Kernel
{
    public function __construct(private Router $router)
    {
    }

    public function handle(Request $req): Response
    {
        App::setRequest($req);
        try {
            Session::start($req->isHttps());
            $mismatch = false;
            $match = $this->router->match($req->method, $req->path, $mismatch);
            if (!$match) {
                throw new HttpException($mismatch ? 405 : 404);
            }
            [$handler, $params, $opts] = $match;
            $req->params = $params;

            if ($req->method === 'POST' && ($opts['csrf'] ?? true) && !Session::verifyCsrf($req->post['_csrf'] ?? null)) {
                throw new HttpException(419);
            }
            if (!empty($opts['admin'])) {
                $user = Container::auth()->user();
                if (!$user) {
                    if ($req->method === 'GET') {
                        Session::set('admin_intended', $req->path . ($req->query ? '?' . http_build_query($req->query) : ''));
                    }
                    return Response::redirect('/admin/login');
                }
                if (($opts['role'] ?? null) === 'super' && $user['role'] !== 'super') {
                    throw new HttpException(403, 'Only senior administrators can open this page.');
                }
            }
            $res = is_array($handler) ? (new $handler[0]())->{$handler[1]}($req) : $handler($req);
        } catch (HttpException $e) {
            $res = $this->errorPage($e->status, $e->getMessage(), $req);
        } catch (\Throwable $e) {
            $ref = strtoupper(bin2hex(random_bytes(4)));
            Logger::error('Unhandled exception ' . $ref . ': ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'path' => $req->path]);
            $msg = 'Sorry, something went wrong on our side. Your information has not been lost. Please try again, or contact the temple office and mention error reference ' . $ref . '.';
            if (App::debug()) {
                $msg .= "\n\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $res = $this->errorPage(500, $msg, $req);
        }
        $this->securityHeaders($res, $req);
        return $res;
    }

    private function errorPage(int $status, string $message, Request $req): Response
    {
        if (str_starts_with($req->path, '/api/') || str_starts_with($req->path, '/webhooks/') || str_contains($req->path, '/gateway/')) {
            return Response::json(['error' => $message], $status);
        }
        try {
            $html = View::render('error', ['status' => $status, 'message' => $message, 'title' => 'Error ' . $status]);
        } catch (\Throwable) {
            $html = '<h1>Error ' . $status . '</h1><p>' . e($message) . '</p>';
        }
        return Response::html($html, $status);
    }

    private function securityHeaders(Response $res, Request $req): void
    {
        $gateway = Env::get('PAYMENT_GATEWAY') === 'razorpay';
        $script = "'self'" . ($gateway ? ' https://checkout.razorpay.com' : '');
        $frame = $gateway ? 'https://api.razorpay.com https://checkout.razorpay.com' : "'none'";
        $connect = "'self'" . ($gateway ? ' https://lumberjack.razorpay.com https://api.razorpay.com' : '');
        $res->headers += [
            'Content-Security-Policy' => "default-src 'self'; script-src $script; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src $connect; frame-src $frame; frame-ancestors 'self'; base-uri 'none'; form-action 'self'; object-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        if (!App::isProduction()) {
            $res->headers['X-Robots-Tag'] = 'noindex, nofollow'; // keep staging out of search engines
        }
        if ($req->isHttps()) {
            $res->headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        if (!isset($res->headers['Cache-Control'])) {
            $res->headers['Cache-Control'] = 'no-store';
        }
    }
}
