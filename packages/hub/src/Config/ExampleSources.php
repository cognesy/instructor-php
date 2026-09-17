<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ExampleSource> */
final class ExampleSources implements IteratorAggregate
{
    /** @var list<ExampleSource> */
    private array $sources;

    /**
     * @param list<ExampleSource> $sources
     */
    private function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    /**
     * @param array<array-key, ExampleSource> $sources
     */
    public static function fromArray(array $sources): self
    {
        return new self(array_values($sources));
    }

    public static function legacy(string $path): self
    {
        return new self([
            ExampleSource::fromPath('legacy', $path),
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }

    /**
     * @return Traversable<int, ExampleSource>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->sources;
    }

    /**
     * @return list<ExampleSource>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
