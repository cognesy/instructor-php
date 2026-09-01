<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

/** Monotonic clock boundary for deterministic Tell execution-limit tests. */
interface CanReadTellClock
{
    public function nowMs(): int;
}
