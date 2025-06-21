<?php

namespace Cabanga\Smail;

class Router
{
    private array $routes = [];

    public function post(string $path, callable $callback): void {
        $this->routes['POST'][$path] = $callback;
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (isset($this->routes[$method][$path])) {
            $this->routes[$method][$path]();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
            exit;
        }
    }
}