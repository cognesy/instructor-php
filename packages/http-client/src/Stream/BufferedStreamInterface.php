<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

/**
 * Buffered stream wrapper - lazy consumption with replay capability.
 *
 * Wraps any iterable and automatically buffers data as it's consumed.
 * Acts as both:
 * - Stream proxy: iterate to consume source (lazy)
 * - Data object: access buffered data via readers (immediate)
 *
 * Use cases:
 * - Stream LLM responses while buffering for later access
 * - Allow multiple consumers of same stream (one active, others from buffer)
 * - Replay stream without re-executing source
 */
interface BufferedStreamInterface extends \IteratorAggregate
{
    /**
     * Get iterator for lazy consumption.
     * First call: streams from source and buffers
     * Subsequent calls: streams from buffer
     *
     * @return \Traversable<string>
     */
    public function getIterator(): \Traversable;

    /**
     * Check if source stream is fully consumed.
     */
    public function isCompleted(): bool;

    public function receivedData() : array;
}
