<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Utils\Result\Result;

/**
 * Collection of HTTP responses from a pool execution
 *
 * Manages response collection with guaranteed ordering.
 * Responses are indexed by their original request position
 * and finalized in sorted order.
 */
final class PoolResponses
{
    /** @var array<int, Result> Indexed by original request position */
    private array $items = [];

    /**
     * Store a response at the given index
     *
     * @param int $index Position in original request array
     * @param Result $result Success or failure result
     */
    public function set(int $index, Result $result): void {
        $this->items[$index] = $result;
    }

    /**
     * Get the number of responses collected
     */
    public function count(): int {
        return count($this->items);
    }

    /**
     * Finalize responses in original request order
     *
     * @return array<Result> Results in stable order matching input requests
     */
    public function finalize(): array {
        ksort($this->items);
        return array_values($this->items);
    }
}
