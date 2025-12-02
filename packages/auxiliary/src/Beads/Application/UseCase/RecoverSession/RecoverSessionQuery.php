<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

/**
 * Recover Session Query
 *
 * Query to recover an agent's session context.
 */
final readonly class RecoverSessionQuery
{
    public function __construct(
        public Agent $agent,
    ) {}
}
