<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// entrypoint
require 'vendor/autoload.php';

use Cabanga\Smail\ContactFormHandler;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); //204 No Content
        exit;
    }
    $handler = new ContactFormHandler();
    $handler->process();
} catch (Throwable $e) {
    file_put_contents('logs/error.log', date('Y-m-d H:i:s') . " - Erro ao enviar e-mail: $e\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno no servidor',
    ]);
}