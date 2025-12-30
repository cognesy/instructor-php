<?php declare(strict_types=1);

namespace Cognesy\Utils\Buffer;

/**
 * @template T
 * @extends BufferInterface<T>
 */
interface BoundedBufferInterface extends BufferInterface
{
    public function isFull(): bool;

    public function capacity(): int;
}