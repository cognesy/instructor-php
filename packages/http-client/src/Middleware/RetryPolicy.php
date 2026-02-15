<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxRetries = 3,
        public int $baseDelayMs = 250,
        public int $maxDelayMs = 8000,
        public string $jitter = 'full', // none|full|equal
        /** @var list<int> */
        public array $retryOnStatus = [408, 429, 500, 502, 503, 504],
        /** @var list<class-string<\Throwable>> */
        public array $retryOnExceptions = [
            TimeoutException::class,
            NetworkException::class,
        ],
        public bool $respectRetryAfter = true,
    ) {}

    public function shouldRetryException(\Throwable $error, int $attemptNumber): bool {
        if ($attemptNumber > max(0, $this->maxRetries)) {
            return false;
        }

        if ($error instanceof HttpRequestException) {
            $status = $error->getStatusCode();
            if ($status !== null && in_array($status, $this->retryOnStatus, true)) {
                return true;
            }
        }

        foreach ($this->retryOnExceptions as $exceptionClass) {
            if ($error instanceof $exceptionClass) {
                return true;
            }
        }

        return $error instanceof HttpRequestException && $error->isRetriable();
    }

    public function shouldRetryResponse(HttpResponse $response, int $attemptNumber): bool {
        if ($attemptNumber > max(0, $this->maxRetries)) {
            return false;
        }

        if ($response->isStreamed()) {
            return false;
        }

        return in_array($response->statusCode(), $this->retryOnStatus, true);
    }

    public function delayMsForAttempt(int $attemptNumber, ?HttpResponse $response = null): int {
        $attempt = max(1, $attemptNumber);
        $base = $this->baseDelayMs * (2 ** ($attempt - 1));
        $capped = (int) min($base, $this->maxDelayMs);

        $delay = match ($this->jitter) {
            'none' => $capped,
            'equal' => (int) ($capped / 2 + random_int(0, (int) ($capped / 2))),
            default => random_int(0, $capped),
        };

        if ($this->respectRetryAfter && $response !== null) {
            $retryAfter = $this->retryAfterSeconds($response);
            if ($retryAfter !== null) {
                $delay = max($delay, $retryAfter * 1000);
            }
        }

        return $delay;
    }

    private function retryAfterSeconds(HttpResponse $response): ?int {
        $headers = $response->headers();
        $value = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }
        $delta = $timestamp - time();
        return $delta > 0 ? $delta : null;
    }
}
