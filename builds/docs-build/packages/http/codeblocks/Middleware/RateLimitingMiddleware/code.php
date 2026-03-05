<?php declare(strict_types=1);

namespace Middleware\RateLimitingMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class RateLimitingMiddleware implements HttpMiddleware
{
    /** @var list<int> */
    private array $requestTimes = [];

    public function __construct(
        private int $maxRequests = 60,
        private int $perSeconds = 60,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $this->removeExpired();

        if (count($this->requestTimes) >= $this->maxRequests) {
            $waitFor = ($this->requestTimes[0] + $this->perSeconds) - time();
            if ($waitFor > 0) {
                sleep($waitFor);
            }
            $this->removeExpired();
        }

        $this->requestTimes[] = time();

        return $next->handle($request);
    }

    private function removeExpired(): void
    {
        $cutoff = time() - $this->perSeconds;
        $this->requestTimes = array_values(array_filter(
            $this->requestTimes,
            static fn(int $t): bool => $t > $cutoff,
        ));
    }
}
