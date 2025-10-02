<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2;

use ArrayIterator;
use Cognesy\Utils\Collection\ArrayList;

/**
 * An immutable, serializable definition of a pipeline.
 *
 * It is a collection of OperatorSpec objects, representing the sequence
 * of operations to be performed. This object is execution-agnostic.
 */
readonly final class PipelineDefinition implements \IteratorAggregate, \Countable
{
    /** @var array<Op> */
    private ArrayList $operators;

    public function __construct(Op ...$operators) {
        $this->operators = ArrayList::of($operators);
    }

    public static function from(Op ...$operators): self {
        return new self(...$operators);
    }

    #[\Override]
    public function getIterator(): ArrayIterator {
        return $this->operators->getIterator();
    }

    #[\Override]
    public function count(): int {
        return $this->operators->count();
    }
}
