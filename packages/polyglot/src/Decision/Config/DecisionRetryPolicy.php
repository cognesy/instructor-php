<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Config;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Support\Retry\RetryAfter;
use Cognesy\Polyglot\Support\Retry\RetryBackoff;
use Cognesy\Polyglot\Support\Retry\RetryJitter;
use Cognesy\Polyglot\Support\Retry\RetryPolicyInvariants;

final readonly class DecisionRetryPolicy
{
    /** @var list<int> */
    private const array DEFAULT_RETRY_ON_STATUS = [408, 429, 500, 502, 503, 504, 529];

    /** @var list<class-string<\Throwable>> */
    private const array DEFAULT_RETRY_ON_EXCEPTIONS = [TimeoutException::class, NetworkException::class];

    public RetryJitter $jitterMode;

    public function __construct(
        public int $maxAttempts = 1,
        public int $baseDelayMs = 250,
        public int $maxDelayMs = 8000,
        public string $jitter = 'full',
        /** @var list<int> */
        public array $retryOnStatus = self::DEFAULT_RETRY_ON_STATUS,
        /** @var list<class-string<\Throwable>> */
        public array $retryOnExceptions = self::DEFAULT_RETRY_ON_EXCEPTIONS,
        public bool $respectRetryAfter = true,
    ) {
        RetryPolicyInvariants::assertMaxAttempts($maxAttempts);
        RetryPolicyInvariants::assertDelays($baseDelayMs, $maxDelayMs);
        RetryPolicyInvariants::assertStatusList($retryOnStatus);
        RetryPolicyInvariants::assertExceptionList($retryOnExceptions);
        $this->jitterMode = RetryJitter::fromString($jitter);
    }

    public function shouldRetry(
        \Throwable $error,
        int $attemptNumber,
    ): bool {
        if ($attemptNumber >= $this->maxAttempts) {
            return false;
        }
        if ($error instanceof DecisionProviderException) {
            return $error->isRetriable();
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

    public function delayMsForAttempt(\Throwable $error, int $attemptNumber, ?int $now = null): int
    {
        $backoff = RetryBackoff::delayMs(
            $attemptNumber,
            $this->baseDelayMs,
            $this->maxDelayMs,
            $this->jitterMode,
        );
        $retryAfter = match (true) {
            $this->respectRetryAfter && $error instanceof DecisionProviderException => RetryAfter::delayMs($error->retryAfter, $this->maxDelayMs, $now),
            default => 0,
        };

        return min($this->maxDelayMs, max($backoff, $retryAfter));
    }

    public function toArray(): array
    {
        return [
            'maxAttempts' => $this->maxAttempts,
            'baseDelayMs' => $this->baseDelayMs,
            'maxDelayMs' => $this->maxDelayMs,
            'jitter' => $this->jitter,
            'retryOnStatus' => $this->retryOnStatus,
            'retryOnExceptions' => $this->retryOnExceptions,
            'respectRetryAfter' => $this->respectRetryAfter,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            maxAttempts: (int) ($data['maxAttempts'] ?? $data['max_attempts'] ?? 1),
            baseDelayMs: (int) ($data['baseDelayMs'] ?? $data['base_delay_ms'] ?? 250),
            maxDelayMs: (int) ($data['maxDelayMs'] ?? $data['max_delay_ms'] ?? 8000),
            jitter: (string) ($data['jitter'] ?? 'full'),
            retryOnStatus: self::listValue(
                $data,
                'retryOnStatus',
                'retry_on_status',
                self::DEFAULT_RETRY_ON_STATUS,
            ),
            retryOnExceptions: self::listValue(
                $data,
                'retryOnExceptions',
                'retry_on_exceptions',
                self::DEFAULT_RETRY_ON_EXCEPTIONS,
            ),
            respectRetryAfter: (bool) ($data['respectRetryAfter'] ?? $data['respect_retry_after'] ?? true),
        );
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @param  list<mixed>  $default
     * @return list<mixed>
     */
    private static function listValue(array $data, string $camel, string $snake, array $default): array
    {
        $value = match (true) {
            array_key_exists($camel, $data) => $data[$camel],
            array_key_exists($snake, $data) => $data[$snake],
            default => $default,
        };
        if (! is_array($value)) {
            throw new \InvalidArgumentException("Invalid retry {$camel}: expected array, got ".get_debug_type($value).'.');
        }

        return array_values($value);
    }
}
