<?php

namespace Cabanga\Smail\Http;

class Request {
    public function __construct(
        public readonly string $method,
        public readonly string $origin,
        public readonly string $contentType,
        public readonly array $body,
        public readonly array $headers = []
    ) {}

    public static function createFromGlobals(): self {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_SERVER['HTTP_ORIGIN'] ?? '',
            $_SERVER['CONTENT_TYPE'] ?? '',
            json_decode(file_get_contents('php://input'), true) ?? [],
            $headers
        );
    }

    public function get(string $key, $default = null) {
        return $this->body[$key] ?? $default;
    }

    public function getHeader(string $name): string
    {
        $normalizedName = strtolower($name);
        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $normalizedName) {
                return $headerValue;
            }
        }
        return '';
    }
}