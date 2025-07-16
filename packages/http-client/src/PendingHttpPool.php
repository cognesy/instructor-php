<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;

/**
 * Deferred pool execution for HTTP requests.
 * Holds requests and pool handler but doesn't execute until methods are called.
 */
class PendingHttpPool
{
    /** @var HttpRequest[] */
    private readonly array $requests;
    private readonly CanHandleRequestPool $poolHandler;

    /**
     * @param HttpRequest[] $requests
     */
    public function __construct(
        array $requests,
        CanHandleRequestPool $poolHandler,
    ) {
        $this->requests = $requests;
        $this->poolHandler = $poolHandler;
    }

    /**
     * Execute all requests in the pool concurrently.
     * 
     * @param int|null $maxConcurrent Maximum number of concurrent requests
     * @return array Array of Result objects containing HttpResponse or exceptions
     */
    public function all(?int $maxConcurrent = null): array {
        return $this->poolHandler->pool($this->requests, $maxConcurrent);
    }
}