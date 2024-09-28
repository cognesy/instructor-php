<?php
namespace Cognesy\Instructor\Extras\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CanHandleHttp
{
    public function handle(string $url, array $headers, array $body, string $method = 'POST', bool $streaming = false) : ResponseInterface;
}
