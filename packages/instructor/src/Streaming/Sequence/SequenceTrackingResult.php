<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Sequence;

/**
 * Result of consuming a Sequenceable update.
 *
 * Contains the advanced tracker and a list of individual completed items
 * (not Sequence snapshots).
 */
final readonly class SequenceTrackingResult
{
    /**
     * @param SequenceTracker $tracker Advanced tracker state.
     * @param list<mixed> $updates Individual completed items.
     */
    public function __construct(
        public SequenceTracker $tracker,
        public array $updates,
    ) {}
}
