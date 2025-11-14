<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;

interface CanHandleRequestPool
{
    /**
     * Handle a pool of HTTP requests concurrently.
     *
     * @param HttpRequestList $requests A collection of HttpRequest objects to be processed.
     * @param int|null $maxConcurrent The maximum number of requests to process concurrently. If null, defaults to config value.
     * @return HttpResponseList A collection of Result objects containing HttpResponse or exceptions.
     */
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList;
}