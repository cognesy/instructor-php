<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that partitions input items into groups based on a specified grouping function,
 * and applies a given reducer to each partition.
 *
 * Example:
 * [10, 11, 21, 22, 33]
 * with getGroupFn = fn($x) => intdiv($x, 10)
 * partitions into:
 * [[10, 11], [21, 22], [33]]
 * and applies the given reducer to each partition.
 */
final class PartitionByReducer implements Reducer
{
    private mixed $currentGroup = null;
    private array $currentPartition = [];

    /**
     * @param Closure(mixed): (string|int) $getGroupFn
     */
    public function __construct(
        private Closure $getGroupFn,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $groupKey = ($this->getGroupFn)($reducible);

        if ($this->currentGroup === null || $groupKey !== $this->currentGroup) {
            if (!empty($this->currentPartition)) {
                $accumulator = $this->reducer->step($accumulator, $this->currentPartition);
            }
            $this->currentGroup = $groupKey;
            $this->currentPartition = [];
        }

        $this->currentPartition[] = $reducible;
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if (!empty($this->currentPartition)) {
            $accumulator = $this->reducer->step($accumulator, $this->currentPartition);
            $this->currentPartition = [];
        }
        return $this->reducer->complete($accumulator);
    }
}
