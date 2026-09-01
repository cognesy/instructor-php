<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Arena;

use Cognesy\Tell\Core\Workspace\Arena\Record\Turn;

/**
 * One verified immutable turn in chronological arena history.
 */
final readonly class HistoryTurn
{
    public function __construct(
        public ObjectHash $hash,
        public Turn $turn,
    ) {}
}
