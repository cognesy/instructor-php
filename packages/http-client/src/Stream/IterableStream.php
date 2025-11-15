<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

/**
 * IterableStream: non-buffering wrapper over any iterable<string> source.
 *
 * Yields chunks directly from the underlying iterable and marks completion
 * after the first full iteration. No replay support by design.
 */
final class IterableStream implements StreamInterface
{
    private bool $completed = false;

    /**
     * @param iterable<string> $source
     */
    public function __construct(
        private iterable $source,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable {
        try {
            foreach ($this->source as $chunk) {
                yield $chunk;
            }
        } finally {
            $this->completed = true;
        }
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->completed;
    }
}

