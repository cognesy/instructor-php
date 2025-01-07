<?php
namespace Cognesy\Instructor\Features\Http\Contracts;

use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;

interface CanHandleHttp
{
    public function handle(HttpClientRequest $request) : CanAccessResponse;
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}
