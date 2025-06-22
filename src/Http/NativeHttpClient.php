<?php

namespace Cabanga\Smail\Http;

use Cabanga\Smail\interfaces\HttpClientInterface;

class NativeHttpClient implements HttpClientInterface
{
    public function post(string $url, array $data): string|false
    {
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}