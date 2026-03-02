<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use LogicException;

final class BufferedStream implements StreamInterface
{
    /** @var list<string> */
    private array $chunks;
    private bool $isCompleted;
    private bool $isStreaming = false;
    private bool $wasInterrupted = false;
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
            $this->wasInterrupted => throw new LogicException('Buffered stream was not fully consumed and cannot be replayed. Consume it fully in a single pass.'),
            $this->isStreaming => throw new LogicException('Buffered stream is currently being consumed and cannot be iterated concurrently.'),
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
        $this->isStreaming = true;
        $consumedFully = false;

        try {
            foreach ($this->source as $chunk) {
                $this->chunks[] = $chunk;
                yield $chunk;
            }
            $consumedFully = true;
        } finally {
            $this->isStreaming = false;
            $this->isCompleted = $consumedFully;
            $this->wasInterrupted = !$consumedFully;
        }
    }
}
