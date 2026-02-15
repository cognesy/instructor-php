<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\InstructorHub\Data\ExampleLocation;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ExampleMatchRule> */
final class ExampleMatchRules implements IteratorAggregate
{
    /** @var ExampleMatchRule[] */
    private array $rules;

    /**
     * @param ExampleMatchRule[] $rules
     */
    private function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * @param ExampleMatchRule[] $rules
     */
    public static function fromArray(array $rules): self
    {
        return new self($rules);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    public function matches(ExampleLocation $location): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->rules as $rule) {
            if ($rule->matches($location)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Traversable<ExampleMatchRule>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->rules;
    }
}
