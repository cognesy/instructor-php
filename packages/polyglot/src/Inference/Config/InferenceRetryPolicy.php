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

    public static function fromOptions(array $options): self {
        $retryPolicy = $options['retryPolicy'] ?? [];
        if (!is_array($retryPolicy)) {
            $retryPolicy = [];
        }

        return new self(
            maxAttempts: (int) ($retryPolicy['maxAttempts'] ?? 1),
            baseDelayMs: (int) ($retryPolicy['baseDelayMs'] ?? 250),
            maxDelayMs: (int) ($retryPolicy['maxDelayMs'] ?? 8000),
            jitter: (string) ($retryPolicy['jitter'] ?? 'full'),
            retryOnStatus: array_values($retryPolicy['retryOnStatus'] ?? [408, 429, 500, 502, 503, 504]),
            retryOnExceptions: array_values($retryPolicy['retryOnExceptions'] ?? [
                TimeoutException::class,
                NetworkException::class,
            ]),
            lengthRecovery: (string) ($retryPolicy['lengthRecovery'] ?? 'none'),
            lengthMaxAttempts: (int) ($retryPolicy['lengthMaxAttempts'] ?? 1),
            lengthContinuePrompt: (string) ($retryPolicy['lengthContinuePrompt'] ?? 'Continue.'),
            maxTokensIncrement: (int) ($retryPolicy['maxTokensIncrement'] ?? 512),
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
        $capped = min($base, $this->maxDelayMs);

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
