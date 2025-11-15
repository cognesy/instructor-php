<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Data\HttpRequest;

/**
 * Mutable state for pool execution
 *
 * Encapsulates all the state needed during pool execution,
 * reducing parameter passing and making the flow clearer.
 *
 * This is intentionally mutable and scoped to a single pool() execution.
 */
final class PoolState
{
    /** @var array<HttpRequest> */
    public readonly array $queue;
    public int $nextIndex = 0;

    public function __construct(
        array $requests,
        public readonly int $maxConcurrent,
        public readonly ActiveTransfers $activeTransfers,
        public readonly PoolResponses $responses,
    ) {
        $this->queue = array_values($requests);
    }

    /**
     * Check if there are more requests to queue
     */
    public function hasMoreRequests(): bool {
        return $this->nextIndex < count($this->queue);
    }

    /**
     * Get the next request and advance the index
     */
    public function nextRequest(): ?HttpRequest {
        if (!$this->hasMoreRequests()) {
            return null;
        }

        $request = $this->queue[$this->nextIndex];
        $this->nextIndex++;
        return $request;
    }

    /**
     * Get the current request index (for the last returned request)
     */
    public function currentIndex(): int {
        return $this->nextIndex - 1;
    }

    /**
     * Check if pool execution is complete
     */
    public function isComplete(): bool {
        return !$this->hasMoreRequests() && $this->activeTransfers->isEmpty();
    }
}
