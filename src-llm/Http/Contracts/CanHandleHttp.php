<?php
namespace Cognesy\LLM\Http\Contracts;

use Cognesy\LLM\Http\Data\HttpClientRequest;

interface CanHandleHttp
{
    public function handle(HttpClientRequest $request) : ResponseAdapter;
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}
