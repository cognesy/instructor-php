<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

/**
 * Manages a stack of processors.
 *
 * Simple container for ProcessorInterface instances.
 */
class ProcessorStack
{
    /** @var ProcessorInterface[] */
    private array $processors = [];

    public function __construct(array $processors = []) {
        $this->processors = $processors;
    }

    public function add(ProcessorInterface $processor): void {
        $this->processors[] = $processor;
    }

    public function addAll(array $processors): void {
        foreach ($processors as $processor) {
            if (!$processor instanceof ProcessorInterface) {
                throw new \InvalidArgumentException('All processors must implement ProcessorInterface');
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

    /**
     * Get all processors as array (for compatibility with existing code).
     */
    public function getProcessors(): array {
        return $this->processors;
    }

    /**
     * Get all processors as an iterable.
     * @return \Traversable<ProcessorInterface>
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->processors);
    }

}