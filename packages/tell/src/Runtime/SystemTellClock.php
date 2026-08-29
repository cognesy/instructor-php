<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Override;

final readonly class SystemTellClock implements CanReadTellClock
{
    #[Override]
    public function nowMs(): int {
        return intdiv(hrtime(true), 1_000_000);
    }
}
