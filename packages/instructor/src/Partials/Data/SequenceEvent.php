<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

use Cognesy\Instructor\Contracts\Sequenceable;

/**
 * Represents a sequence update event.
 * Emitted by SequenceUpdates transducer.
 *
 * @template TSequence of Sequenceable
 */
final readonly class SequenceEvent
{
    /**
     * @param TSequence $item
     */
    public function __construct(
        public SequenceEventType $type,
        public Sequenceable $item,
        public int $index,
    ) {}
}
