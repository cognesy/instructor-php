<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

/**
 * Stream interface
 */
interface StreamInterface extends \IteratorAggregate
{
    /** @return \Traversable<string> */
    public function getIterator(): \Traversable;

    /**
     * Check if source stream is fully consumed.
     */
    public function isCompleted(): bool;

    public function receivedData() : array;
}
