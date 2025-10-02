<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline;

use ArrayIterator;

/**
 * An immutable, serializable definition of a pipeline.
 *
 * It is a collection of OperatorSpec objects, representing the sequence
 * of operations to be performed. This object is execution-agnostic.
 */
readonly final class PipelineDefinition implements \IteratorAggregate, \Countable
{
    /** @var array<OperatorSpec> */
    private array $operators;

    public function __construct(OperatorSpec ...$operators) {
        $this->operators = $operators;
    }

    public static function from(OperatorSpec ...$operators): self {
        return new self(...$operators);
    }

    #[\Override]
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->operators);
    }

    #[\Override]
    public function count(): int {
        return count($this->operators);
    }
}
