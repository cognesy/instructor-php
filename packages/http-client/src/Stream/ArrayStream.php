<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

/**
 * ArrayStream: simple, low-overhead stream over a list of string chunks.
 *
 * - Non-buffering beyond the provided array
 * - Allows multiple iterations (replays the provided array)
 * - Marks completed after the first full iteration
 */
final class ArrayStream implements StreamInterface
{
    /** @var list<string> */
    private array $chunks;
    private bool $completed = false;

    /**
     * @param list<string> $chunks
     */
    public function __construct(array $chunks) {
        $this->chunks = $chunks;
    }

    public static function from(array $chunks): self {
        return new self($chunks);
    }

    /** @return \Traversable<string> */
    #[\Override]
    public function getIterator(): \Traversable {
        try {
            foreach ($this->chunks as $chunk) {
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

