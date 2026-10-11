<?php
declare(strict_types=1);

namespace SJT\Core;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable|array,3:array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $opts = []): void
    {
        $this->routes[] = ['GET', $pattern, $handler, $opts];
    }

    public function post(string $pattern, callable|array $handler, array $opts = []): void
    {
        $this->routes[] = ['POST', $pattern, $handler, $opts];
    }

    /** @return array{0:callable|array,1:array,2:array}|null handler, params, opts */
    public function match(string $method, string $path, bool &$methodMismatch = false): ?array
    {
        foreach ($this->routes as [$m, $pattern, $handler, $opts]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $mm)) {
                if ($m !== $method && !($method === 'HEAD' && $m === 'GET')) {
                    $methodMismatch = true;
                    continue;
                }
                $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params, $opts];
            }
        }
        return null;
    }
}
