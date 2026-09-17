<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

use Override;

final class SystemRetryDelay implements CanDelayRetries
{
    #[Override]
    public function delay(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
