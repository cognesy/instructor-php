<?php
namespace Cognesy\Polyglot\Http\Contracts;

use Cognesy\Polyglot\Http\Data\HttpClientRequest;

interface CanHandleHttp
{
    public function handle(HttpClientRequest $request) : ResponseAdapter;
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}
