<?php
namespace Book100\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void { $this->routes['GET'][] = [$pattern, $handler]; }
    public function post(string $pattern, callable $handler): void { $this->routes['POST'][] = [$pattern, $handler]; }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = rtrim(parse_url($path, PHP_URL_PATH) ?: '/', '/') ?: '/';
        foreach ($this->routes[$method] ?? [] as [$pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/') . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...array_values($params));
                return;
            }
        }
        http_response_code(404);
        echo '404';
    }
}
