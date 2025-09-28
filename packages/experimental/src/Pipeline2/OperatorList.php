<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2;

use Cognesy\Experimental\Pipeline2\Contracts\Operator;
use Cognesy\Utils\Collection\ArrayList;

final readonly class OperatorList implements \Countable, \IteratorAggregate
{
    private ArrayList $operators;

    public function __construct(Operator ...$operators) {
        $this->operators = ArrayList::of($operators);
    }

    public static function with(Operator ...$operators) : self {
        return new self(...$operators);
    }

    public function hasItemAt(int $index): bool {
        return $index >= 0 && $index < $this->operators->count();
    }

    public function itemAt(int $index): ?Operator {
        return match(true) {
            $this->hasItemAt($index) => $this->operators->itemAt($index),
            default => null,
        };
    }

    public function getIterator(): \Traversable {
        yield from $this->operators;
    }

    public function count(): int {
        return $this->operators->count();
    }
}