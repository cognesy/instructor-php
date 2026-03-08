<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Sequence;

use Cognesy\Utils\Profiler\TracksObjectCreation;

/**
 * Result of consuming a Sequenceable update with SequenceTracker.
 */
final readonly class SequenceTrackingResult
{
    use TracksObjectCreation;

    public function __construct(
        public SequenceTracker $tracker,
        public SequenceUpdateList $updates,
    ) {
        $this->trackObjectCreation();
    }
}
