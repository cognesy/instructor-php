<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

final class BufferedStream implements StreamInterface
{
    /** @var list<string> */
    private array $chunks;
    private bool $isCompleted;
    /** @var iterable<string> */
    private iterable $source;

    private function __construct(
        ?iterable $source = null,
        bool $isCompleted = false,
        array $chunks = [],
    ) {
        $this->source = $source ?? [];
        $this->isCompleted = $isCompleted;
        $this->chunks = $chunks;
    }

    public static function empty() : self {
        return new self(
            isCompleted: true,
            chunks: [],
        );
    }

    public static function fromStream(iterable $source): self {
        return new self(
            source: $source,
        );
    }

    public static function fromArray(array $stream) : self {
        return new self(
            isCompleted: true,
            chunks: $stream,
        );
    }

    #[\Override]
    public function getIterator(): \Traversable {
        return match (true) {
            $this->isCompleted => $this->iterateCached(),
            default => $this->iterateIncoming(),
        };
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->isCompleted;
    }

    // INTERNAL ////////////////////////////////////////////////////

    /** @return \Traversable<string> */
    private function iterateCached(): \Traversable {
        yield from $this->chunks;
    }

    /** @return \Traversable<string> */
    private function iterateIncoming(): \Traversable {
        try {
            foreach ($this->source as $chunk) {
                $this->chunks[] = $chunk;
                yield $chunk;
            }
        } finally {
            $this->isCompleted = true;
        }
    }
}