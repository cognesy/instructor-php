<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain;

/**
 * Result of consuming a Sequenceable update with SequenceTracker.
 */
final readonly class SequenceTrackingResult
{
    public function __construct(
        public SequenceTracker $tracker,
        public SequenceUpdateList $updates,
    ) {}
}
