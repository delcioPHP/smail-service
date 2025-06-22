<?php

namespace Cabanga\Smail\interfaces;

interface HttpClientInterface
{
    /**
     * Sends a POST request to a URL.
     * @param string $url The URL to send the request to.
     * @param array $data The data to be sent in the request body.
     * @return string|false The response body or false on failure.
     */
    public function post(string $url, array $data): string|false;
}