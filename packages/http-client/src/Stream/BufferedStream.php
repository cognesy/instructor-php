<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

/**
 * Buffered stream wrapper - lazy consumption with automatic buffering.
 *
 * Wraps any iterable source and automatically buffers chunks as they're consumed.
 * Supports:
 * - Lazy iteration: source consumed on-demand during first iteration
 * - Replay: subsequent iterations read from buffer
 * - Multiple readers: independent cursors into buffered data
 * - Early access: readers can access buffered data before full consumption
 *
 * Thread-safe: source consumed once, buffer is append-only.
 */
final class BufferedStream implements BufferedStreamInterface
{
    /** @var list<string> */
    private array $chunks;
    private bool $isCompleted;
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

    public static function fromStream(iterable $source): self {
        return new self(
            source: $source,
        );
    }

    public static function empty() : self {
        return new self(
            isCompleted: true,
            chunks: [],
        );
    }

    public static function fromArray(array $stream) : self {
        return new self(
            isCompleted: true,
            chunks: $stream,
        );
    }

    public function getIterator(): \Traversable {
        return match (true) {
            $this->isCompleted => $this->iterateCached(),
            default => $this->iterateIncoming(),
        };
    }

    public function isCompleted(): bool {
        return $this->isCompleted;
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function iterateCached(): \Traversable {
        yield from $this->chunks;
    }

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