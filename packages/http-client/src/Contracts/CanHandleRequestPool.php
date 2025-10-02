<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpRequest;

interface CanHandleRequestPool
{
    /**
     * Handle a pool of HTTP requests concurrently.
     *
     * @param array<HttpRequest> $requests An array of HttpRequest objects to be processed.
     * @param int|null $maxConcurrent The maximum number of requests to process concurrently. If null, defaults to the number of requests.
     * @return array An array of HttpResponse objects corresponding to the requests.
     */
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}