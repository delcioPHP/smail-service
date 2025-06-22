<?php

namespace Cabanga\Smail\interfaces;

interface LoggerInterface
{
    public function logSuccess(string $message): void;
    public function logError(string $error): void;
}