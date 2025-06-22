<?php

namespace Cabanga\Smail\Http;

class Request {
    public function __construct(
        public readonly string $method,
        public readonly string $origin,
        public readonly string $contentType,
        public readonly array $body
    ) {}

    public static function createFromGlobals(): self {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_SERVER['HTTP_ORIGIN'] ?? '',
            $_SERVER['CONTENT_TYPE'] ?? '',
            json_decode(file_get_contents('php://input'), true) ?? []
        );
    }

    public function get(string $key, $default = null) {
        return $this->body[$key] ?? $default;
    }
}