<?php

// Preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}

header("Content-Type: application/json");

require __DIR__ . '/../vendor/autoload.php';

use Cabanga\Smail\Config;
use Cabanga\Smail\Router;
use Cabanga\Smail\ContactFormHandler;

try {
    // Load configs
    $config = new Config(__DIR__ . '/../');
    $router = new Router();
    // Define the route based on the .env configuration
    $apiRoute = $config->get('API_ROUTE', '/api/contact');

    $router->post($apiRoute, function() use ($config) {
        $service = new ContactFormHandler($config);
        $service->process();
    });

    $router->dispatch();

} catch (\Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro interno no servidor.'
    ]);
}