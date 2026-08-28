<?php

declare(strict_types=1);

namespace App\Http\Responses;

final class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        http_response_code($response->statusCode());
        foreach ($response->headers() as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->toString();
    }
}
