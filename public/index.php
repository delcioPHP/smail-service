<?php
require __DIR__ . '/../vendor/autoload.php';

// Preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}

header("Content-Type: application/json");

use Cabanga\Smail\Config;
use Cabanga\Smail\Http\Request;
use Cabanga\Smail\Http\Response;
use Cabanga\Smail\Router;
use Cabanga\Smail\ContactFormHandler;
use Cabanga\Smail\Translator;
use PHPMailer\PHPMailer\PHPMailer;

try {
    // Load configs
    $config = new Config(__DIR__ . '/../');
    $request = Request::createFromGlobals();

    $lang = $request->get('lang', $config->get(
        'DEFAULT_LANG', 'en')
    );
    $translator = new Translator($lang, $config->get(
        'DEFAULT_LANG', 'en')
    );

    $router = new Router();
    // Define the route based on the .env configuration
    $apiRoute = $config->get('API_ROUTE', '/api/contact');

    $router->post($apiRoute, function() use ($config, $translator, $request) {
        $service = new ContactFormHandler($config, $translator, $request);
        $mail = new PHPMailer(true);
        $response = $service->process($mail);
        $response->send();
    });

    $router->dispatch();

} catch (\Exception $e) {
    error_log($e->getMessage());
    $response = new Response(
        json_encode(['success' => false, 'message' => 'Server Error']),
        500
    );
    $response->send();
}