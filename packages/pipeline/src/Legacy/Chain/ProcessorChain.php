<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Legacy\Chain;

/**
 * Executes a linear list of callables, passing the output of one as the input
 * to the next.  Stops early if a step returns `null`.
 */
final class ProcessorChain
{
    /** @var list<callable(mixed):mixed> */
    private array $processors;

    /**
     * @param list<callable(mixed):mixed> $processors
     */
    public function __construct(array $processors) {
        if ($processors === []) {
            throw new \InvalidArgumentException('Pipeline requires at least one processor.');
        }
        $this->processors = $processors;
    }

    /**
     * @param mixed $payload
     * @return mixed|null  Returns the final payload or null if any step returned null.
     */
    public function process(mixed $payload): mixed {
        $carry = $payload;
        foreach ($this->processors as $processor) {
            $carry = $processor($carry);
            if ($carry === null) {
                return null;
            }
        }
        return $carry;
    }

    /**
     * Returns the list of processors in the chain.
     *
     * @return list<callable(mixed):mixed>
     */
    public function processors(): array {
        return $this->processors;
    }
}