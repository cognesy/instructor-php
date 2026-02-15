<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ExampleSubgroupDefinition> */
final class ExampleSubgroupDefinitions implements IteratorAggregate
{
    /** @var ExampleSubgroupDefinition[] */
    private array $subgroups;

    /**
     * @param ExampleSubgroupDefinition[] $subgroups
     */
    private function __construct(array $subgroups)
    {
        $this->subgroups = $subgroups;
    }

    /**
     * @param ExampleSubgroupDefinition[] $subgroups
     */
    public static function fromArray(array $subgroups): self
    {
        return new self($subgroups);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return Traversable<ExampleSubgroupDefinition>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->subgroups;
    }
}
