<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use IteratorAggregate;
use Traversable;

/**
 * Stream interface.
 *
 * `isCompleted()` becomes true only after an iterator reaches its natural end.
 * Closing or breaking an iterator early leaves the stream incomplete, even when
 * the underlying source cannot be replayed.
 *
 * @extends IteratorAggregate<int, string>
 */
interface StreamInterface extends IteratorAggregate
{
    /** @return Traversable<int, string> */
    #[\Override]
    public function getIterator(): Traversable;

    /**
     * Check if the source stream was fully consumed.
     */
    public function isCompleted(): bool;
}
