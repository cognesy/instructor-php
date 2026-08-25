<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

/** Monotonic clock boundary for deterministic Tell execution-limit tests. */
interface CanReadTellClock
{
    public function nowMs(): int;
}
