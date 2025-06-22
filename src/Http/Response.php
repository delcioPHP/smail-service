<?php

namespace Cabanga\Smail\Http;

class Response {
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode = 200,
        public readonly array $headers = ['Content-Type' => 'application/json']
    ) {}

    public function send(): void {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        echo $this->body;
    }
}