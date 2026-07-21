<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Config\RetryBackoff;
use Cognesy\Polyglot\Inference\Config\RetryJitter;
use Cognesy\Polyglot\Inference\Config\RetryPolicyInvariants;

final readonly class EmbeddingsRetryPolicy
{
    /** Validated jitter strategy resolved from the string $jitter value. */
    public RetryJitter $jitterMode;

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
    ) {
        RetryPolicyInvariants::assertMaxAttempts($maxAttempts);
        RetryPolicyInvariants::assertDelays($baseDelayMs, $maxDelayMs);
        RetryPolicyInvariants::assertStatusList($retryOnStatus);
        RetryPolicyInvariants::assertExceptionList($retryOnExceptions);
        $this->jitterMode = RetryJitter::fromString($jitter);
    }

    public function shouldRetryException(\Throwable $error, int $attemptNumber): bool {
        if ($attemptNumber > max(1, $this->maxAttempts)) {
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

        if ($error instanceof HttpRequestException) {
            return $error->isRetriable();
        }

        return false;
    }

    public function delayMsForAttempt(int $attemptNumber): int {
        return RetryBackoff::delayMs($attemptNumber, $this->baseDelayMs, $this->maxDelayMs, $this->jitterMode);
    }
}
