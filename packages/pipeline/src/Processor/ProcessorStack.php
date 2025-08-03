<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use ArrayIterator;
use Cognesy\Pipeline\Contracts\CanProcessState;
use InvalidArgumentException;
use Traversable;

/**
 * Manages a stack of processors.
 */
class ProcessorStack
{
    /** @var CanProcessState[] */
    private array $processors;

    /**
     * @param CanProcessState[] $processors
     */
    public function __construct(array $processors = []) {
        $this->processors = $processors;
    }

    public function add(CanProcessState $processor): void {
        $this->processors[] = $processor;
    }

    /**
     * @param CanProcessState[] $processors
     */
    public function addAll(array $processors): void {
        foreach ($processors as $processor) {
            if (!$processor instanceof CanProcessState) {
                throw new InvalidArgumentException('All processors must implement CanProcessState');
            }
            $this->add($processor);
        }
    }

    public function isEmpty(): bool {
        return empty($this->processors);
    }

    public function count(): int {
        return count($this->processors);
    }

    public function all(): array {
        return $this->processors;
    }

    /**
     * @return Traversable<CanProcessState>
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->processors);
    }

}