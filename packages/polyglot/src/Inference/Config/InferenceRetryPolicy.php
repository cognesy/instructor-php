<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;

final readonly class InferenceRetryPolicy
{
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
) {}

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
        $retryOnStatus = $data['retryOnStatus'] ?? $data['retry_on_status'] ?? [408, 429, 500, 502, 503, 504];
        $retryOnExceptions = $data['retryOnExceptions'] ?? $data['retry_on_exceptions'] ?? [
            TimeoutException::class,
            NetworkException::class,
        ];

        return new self(
            maxAttempts: (int) ($data['maxAttempts'] ?? $data['max_attempts'] ?? 1),
            baseDelayMs: (int) ($data['baseDelayMs'] ?? $data['base_delay_ms'] ?? 250),
            maxDelayMs: (int) ($data['maxDelayMs'] ?? $data['max_delay_ms'] ?? 8000),
            jitter: (string) ($data['jitter'] ?? 'full'),
            retryOnStatus: is_array($retryOnStatus) ? array_values($retryOnStatus) : [408, 429, 500, 502, 503, 504],
            retryOnExceptions: is_array($retryOnExceptions) ? array_values($retryOnExceptions) : [
                TimeoutException::class,
                NetworkException::class,
            ],
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
        $attempt = max(1, $attemptNumber);
        $base = $this->baseDelayMs * (2 ** ($attempt - 1));
        $capped = (int) min($base, $this->maxDelayMs);

        return match ($this->jitter) {
            'none' => $capped,
            'equal' => (int) ($capped / 2 + random_int(0, (int) ($capped / 2))),
            default => random_int(0, $capped),
        };
    }

    public function shouldRecoverFromLength(int $lengthAttempts): bool {
        if ($this->lengthRecovery === 'none') {
            return false;
        }
        return $lengthAttempts < max(0, $this->lengthMaxAttempts);
    }
}
