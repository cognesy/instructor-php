<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use IteratorAggregate;
use Traversable;

final class ExampleSources implements IteratorAggregate
{
    /** @var ExampleSource[] */
    private array $sources;

    /**
     * @param ExampleSource[] $sources
     */
    private function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    /**
     * @param ExampleSource[] $sources
     */
    public static function fromArray(array $sources): self
    {
        return new self($sources);
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
     * @return Traversable<ExampleSource>
     */
    public function getIterator(): Traversable
    {
        yield from $this->sources;
    }

    /**
     * @return ExampleSource[]
     */
    public function all(): array
    {
        return $this->sources;
    }
}
