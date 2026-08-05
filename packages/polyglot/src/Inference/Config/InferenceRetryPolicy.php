<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;
use Cognesy\Polyglot\Support\Retry\RetryBackoff;
use Cognesy\Polyglot\Support\Retry\RetryJitter;
use Cognesy\Polyglot\Support\Retry\RetryPolicyInvariants;
use InvalidArgumentException;

final readonly class InferenceRetryPolicy
{
    /** @var list<int> */
    private const array DEFAULT_RETRY_ON_STATUS = [408, 429, 500, 502, 503, 504];
    /** @var list<class-string<\Throwable>> */
    private const array DEFAULT_RETRY_ON_EXCEPTIONS = [
        TimeoutException::class,
        NetworkException::class,
    ];

    /** Validated jitter strategy resolved from the string $jitter value. */
    public RetryJitter $jitterMode;
    /** Validated length recovery strategy resolved from the string $lengthRecovery value. */
    public LengthRecovery $lengthRecoveryMode;

    public function __construct(
        public int $maxAttempts = 1,
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
        public string $lengthRecovery = 'none', // none|continue|increase_max_tokens
        public int $lengthMaxAttempts = 1,
        public string $lengthContinuePrompt = 'Continue.',
        public int $maxTokensIncrement = 512,
    ) {
        RetryPolicyInvariants::assertMaxAttempts($maxAttempts);
        RetryPolicyInvariants::assertDelays($baseDelayMs, $maxDelayMs);
        RetryPolicyInvariants::assertStatusList($retryOnStatus);
        RetryPolicyInvariants::assertExceptionList($retryOnExceptions);
        RetryPolicyInvariants::assertNonNegative($lengthMaxAttempts, 'lengthMaxAttempts');
        RetryPolicyInvariants::assertNonNegative($maxTokensIncrement, 'maxTokensIncrement');
        $this->jitterMode = RetryJitter::fromString($jitter);
        $this->lengthRecoveryMode = LengthRecovery::fromString($lengthRecovery);
    }

    public function toArray(): array {
        return [
            'maxAttempts' => $this->maxAttempts,
            'baseDelayMs' => $this->baseDelayMs,
            'maxDelayMs' => $this->maxDelayMs,
            'jitter' => $this->jitter,
            'retryOnStatus' => $this->retryOnStatus,
            'retryOnExceptions' => $this->retryOnExceptions,
            'lengthRecovery' => $this->lengthRecovery,
            'lengthMaxAttempts' => $this->lengthMaxAttempts,
            'lengthContinuePrompt' => $this->lengthContinuePrompt,
            'maxTokensIncrement' => $this->maxTokensIncrement,
        ];
    }

    public static function fromArray(array $data): self {
        $retryOnStatus = self::listValue(
            data: $data,
            camelCaseKey: 'retryOnStatus',
            snakeCaseKey: 'retry_on_status',
            default: self::DEFAULT_RETRY_ON_STATUS,
        );
        $retryOnExceptions = self::listValue(
            data: $data,
            camelCaseKey: 'retryOnExceptions',
            snakeCaseKey: 'retry_on_exceptions',
            default: self::DEFAULT_RETRY_ON_EXCEPTIONS,
        );

        return new self(
            maxAttempts: (int) ($data['maxAttempts'] ?? $data['max_attempts'] ?? 1),
            baseDelayMs: (int) ($data['baseDelayMs'] ?? $data['base_delay_ms'] ?? 250),
            maxDelayMs: (int) ($data['maxDelayMs'] ?? $data['max_delay_ms'] ?? 8000),
            jitter: (string) ($data['jitter'] ?? 'full'),
            retryOnStatus: $retryOnStatus,
            retryOnExceptions: $retryOnExceptions,
            lengthRecovery: (string) ($data['lengthRecovery'] ?? $data['length_recovery'] ?? 'none'),
            lengthMaxAttempts: (int) ($data['lengthMaxAttempts'] ?? $data['length_max_attempts'] ?? 1),
            lengthContinuePrompt: (string) ($data['lengthContinuePrompt'] ?? $data['length_continue_prompt'] ?? 'Continue.'),
            maxTokensIncrement: (int) ($data['maxTokensIncrement'] ?? $data['max_tokens_increment'] ?? 512),
        );
    }

    public function shouldRetryException(\Throwable $error): bool {
        if ($error instanceof ProviderException) {
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

        if ($error instanceof HttpRequestException) {
            return $error->isRetriable();
        }

        return false;
    }

    public function delayMsForAttempt(int $attemptNumber): int {
        return RetryBackoff::delayMs($attemptNumber, $this->baseDelayMs, $this->maxDelayMs, $this->jitterMode);
    }

    public function shouldRecoverFromLength(int $lengthAttempts): bool {
        if ($this->lengthRecoveryMode === LengthRecovery::None) {
            return false;
        }
        return $lengthAttempts < $this->lengthMaxAttempts;
    }

    /**
     * Camel-case serialized fields take precedence when both aliases are present.
     * Associative arrays remain accepted and are deliberately normalized to lists.
     *
     * @param array<array-key,mixed> $data
     * @param list<mixed> $default
     * @return list<mixed>
     */
    private static function listValue(
        array $data,
        string $camelCaseKey,
        string $snakeCaseKey,
        array $default,
    ): array {
        $key = match (true) {
            array_key_exists($camelCaseKey, $data) => $camelCaseKey,
            array_key_exists($snakeCaseKey, $data) => $snakeCaseKey,
            default => null,
        };
        if ($key === null) {
            return $default;
        }

        $value = $data[$key];
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid retry %s: expected array, got %s.',
                $key,
                get_debug_type($value),
            ));
        }

        return array_values($value);
    }
}
