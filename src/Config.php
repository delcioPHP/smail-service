<?php

namespace Cabanga\Smail;

use Dotenv\Dotenv;

class Config
{
    private array $settings;

    public function __construct(string $path) {
        $dotenv = Dotenv::createImmutable($path);
        $this->settings = $dotenv->load();
    }

    public function get(string $key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}