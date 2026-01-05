<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;

final readonly class CircuitBreakerPolicy
{
    public function __construct(
        public int $failureThreshold = 5,
        public int $openForSec = 30,
        public int $halfOpenMaxRequests = 2,
        public int $successThreshold = 2,
        /** @var list<int> */
        public array $failureStatusCodes = [429, 500, 502, 503, 504],
    ) {}

    public function isFailureResponse(HttpResponse $response): bool {
        if ($response->isStreamed()) {
            return false;
        }
        return in_array($response->statusCode(), $this->failureStatusCodes, true);
    }

    public function isFailureException(\Throwable $error): bool {
        if ($error instanceof HttpRequestException) {
            $status = $error->getStatusCode();
            if ($status !== null && in_array($status, $this->failureStatusCodes, true)) {
                return true;
            }
            return $error->isRetriable();
        }
        return false;
    }
}
