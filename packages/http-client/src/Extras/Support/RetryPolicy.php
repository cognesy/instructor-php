<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use DateTimeImmutable;

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
        public bool $retryNonIdempotentMethods = false,
    ) {}

    public function canRetryRequest(HttpRequest $request): bool {
        if ($this->retryNonIdempotentMethods) {
            return true;
        }

        return in_array(strtoupper($request->method()), [
            'GET',
            'HEAD',
            'PUT',
            'DELETE',
            'OPTIONS',
            'TRACE',
        ], true);
    }

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
        $maxDelayMs = max(0, $this->maxDelayMs);
        $capped = (int) min($base, $maxDelayMs);

        $delay = match ($this->jitter) {
            'none' => $capped,
            'equal' => (int) ($capped / 2 + random_int(0, (int) ($capped / 2))),
            default => random_int(0, $capped),
        };

        if ($this->respectRetryAfter && $response !== null) {
            $retryAfter = $this->retryAfterSeconds($response);
            if ($retryAfter !== null) {
                $delay = max($delay, $this->retryAfterDelayMs($retryAfter, $maxDelayMs));
            }
        }

        return min($delay, $maxDelayMs);
    }

    private function retryAfterSeconds(HttpResponse $response): ?int {
        $value = null;
        foreach ($response->headers() as $name => $headerValue) {
            if (strcasecmp((string) $name, 'Retry-After') === 0) {
                $value = $headerValue;
                break;
            }
        }
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if ($value === null) {
            return null;
        }

        $numericValue = trim((string) $value);
        if ($numericValue !== '' && ctype_digit($numericValue)) {
            $normalizedValue = ltrim($numericValue, '0');
            if ($normalizedValue === '') {
                return 0;
            }

            $maxInteger = (string) PHP_INT_MAX;
            if (strlen($normalizedValue) > strlen($maxInteger)
                || (strlen($normalizedValue) === strlen($maxInteger) && strcmp($normalizedValue, $maxInteger) > 0)
            ) {
                return PHP_INT_MAX;
            }

            return (int) $normalizedValue;
        }

        $httpDate = $numericValue;
        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s T', $httpDate);
        if ($date === false) {
            return null;
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }
        if ($date->format('D, d M Y H:i:s T') !== $httpDate) {
            return null;
        }
        $delta = $date->getTimestamp() - time();
        return $delta > 0 ? $delta : null;
    }

    private function retryAfterDelayMs(int $retryAfterSeconds, int $maxDelayMs): int {
        if ($retryAfterSeconds <= 0 || $maxDelayMs <= 0) {
            return 0;
        }

        $maxWholeSeconds = intdiv($maxDelayMs, 1000);
        if ($retryAfterSeconds > $maxWholeSeconds) {
            return $maxDelayMs;
        }

        return min($maxDelayMs, $retryAfterSeconds * 1000);
    }
}
