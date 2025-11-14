<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Contracts\CanHandleRequestPool;

/**
 * Deferred pool execution for HTTP requests.
 * Holds requests and pool handler but doesn't execute until methods are called.
 */
final readonly class PendingHttpPool
{
    private HttpRequestList $requests;
    private CanHandleRequestPool $poolHandler;

    public function __construct(
        HttpRequestList $requests,
        CanHandleRequestPool $poolHandler,
    ) {
        $this->requests = $requests;
        $this->poolHandler = $poolHandler;
    }

    /**
     * Execute all requests in the pool concurrently.
     *
     * @param int|null $maxConcurrent Maximum number of concurrent requests
     * @return HttpResponseList Collection of Result objects containing HttpResponse or exceptions
     */
    public function all(?int $maxConcurrent = null): HttpResponseList {
        return $this->poolHandler->pool($this->requests, $maxConcurrent);
    }
}