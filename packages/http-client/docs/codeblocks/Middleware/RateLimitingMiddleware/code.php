<?php

namespace Middleware\RateLimitingMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

class RateLimitingMiddleware extends BaseMiddleware
{
    private int $maxRequests;
    private int $perSeconds;
    private array $requestTimes = [];

    public function __construct(int $maxRequests = 60, int $perSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->perSeconds = $perSeconds;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        // Clean up old request times
        $this->removeOldRequestTimes();

        // If we've hit our limit, wait until we can make another request
        if (count($this->requestTimes) >= $this->maxRequests) {
            $oldestRequest = $this->requestTimes[0];
            $timeToWait = $oldestRequest + $this->perSeconds - time();

            if ($timeToWait > 0) {
                sleep($timeToWait);
            }

            // Clean up again after waiting
            $this->removeOldRequestTimes();
        }

        // Record this request time
        $this->requestTimes[] = time();

        // Make the request
        return $next->handle($request);
    }

    private function removeOldRequestTimes(): void
    {
        $cutoff = time() - $this->perSeconds;

        // Remove request times older than our window
        $this->requestTimes = array_filter(
            $this->requestTimes,
            fn($time) => $time > $cutoff
        );

        // Reindex the array
        $this->requestTimes = array_values($this->requestTimes);
    }
}
