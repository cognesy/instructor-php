<?php
namespace Cognesy\Instructor\Features\Http\Contracts;

interface CanHandleHttp
{
    public function handle(string $url, array $headers, array $body, string $method = 'POST', bool $streaming = false) : CanAccessResponse;
}
