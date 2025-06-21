<?php

namespace Cabanga\Smail;

class Translator
{
    private array $translations = [];
    private string $langPath;

    public function __construct(string $language, string $fallbackLanguage = 'en') {
        $this->langPath = __DIR__ . '/../lang/';
        $this->load($language, $fallbackLanguage);
    }

    private function load(string $language, string $fallbackLanguage): void {
        $file = $this->langPath . $language . '.php';
        $fallbackFile = $this->langPath . $fallbackLanguage . '.php';

        if (file_exists($file)) {
            $this->translations = require $file;
        } elseif (file_exists($fallbackFile)) {
            $this->translations = require $fallbackFile;
        }
    }

    /**
     * Obtém uma tradução (Óbvio, pt first).
     *
     * @param string $key A chave da tradução.
     * @param array $replacements Valores para substituir placeholders (%s).
     * @return string
     */
    public function get(string $key, array $replacements = []): string {
        $message = $this->translations[$key] ?? $key;

        if (!empty($replacements)) {
            return sprintf($message, ...$replacements);
        }

        return $message;
    }
}