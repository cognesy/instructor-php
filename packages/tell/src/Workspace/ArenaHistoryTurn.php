<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalTurn;

/**
 * One verified immutable turn in chronological arena history.
 */
final readonly class ArenaHistoryTurn
{
    public function __construct(
        public CanonicalHash $hash,
        public CanonicalTurn $turn,
    ) {}
}
