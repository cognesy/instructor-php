<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use IteratorAggregate;
use Traversable;

/**
 * Stream interface
 *
 * @extends IteratorAggregate<int, string>
 */
interface StreamInterface extends IteratorAggregate
{
    /** @return Traversable<int, string> */
    #[\Override]
    public function getIterator(): Traversable;

    /**
     * Check if source stream is fully consumed.
     */
    public function isCompleted(): bool;
}
