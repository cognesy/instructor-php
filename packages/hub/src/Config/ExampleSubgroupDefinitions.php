<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ExampleSubgroupDefinition> */
final class ExampleSubgroupDefinitions implements IteratorAggregate
{
    /** @var list<ExampleSubgroupDefinition> */
    private array $subgroups;

    /**
     * @param list<ExampleSubgroupDefinition> $subgroups
     */
    private function __construct(array $subgroups)
    {
        $this->subgroups = $subgroups;
    }

    /**
     * @param array<array-key, ExampleSubgroupDefinition> $subgroups
     */
    public static function fromArray(array $subgroups): self
    {
        return new self(array_values($subgroups));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return Traversable<int, ExampleSubgroupDefinition>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->subgroups;
    }
}
