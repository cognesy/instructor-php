<?php
namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CanHandleHttp
{
    public function handle(string $url, array $headers, array $body, bool $streaming = false) : ResponseInterface;
}
