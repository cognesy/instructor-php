<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

/**
 * Get Next Task Query
 *
 * Query to get the next available task for an agent.
 */
final readonly class GetNextTaskQuery
{
    public function __construct(
        public Agent $agent,
        public int $limit = 1,
    ) {}
}
