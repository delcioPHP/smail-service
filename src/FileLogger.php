<?php

namespace Cabanga\Smail;

use Cabanga\Smail\interfaces\LoggerInterface;

class FileLogger implements LoggerInterface
{
    private string $logPath;

    public function __construct(Config $config)
    {
        $this->logPath = $config->get('LOG_PATH', '../logs/');
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0775, true);
        }
    }

    public function logSuccess(string $message): void
    {
        $logFile = $this->logPath . 'contact.log';
        $entry = date('Y-m-d H:i:s') . " - $message\n";
        file_put_contents($logFile, $entry, FILE_APPEND);
    }

    public function logError(string $error): void
    {
        $logFile = $this->logPath . 'error.log';
        $entry = date('Y-m-d H:i:s') . " - Erro: $error\n";
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}