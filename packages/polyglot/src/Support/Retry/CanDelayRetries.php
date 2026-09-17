<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

interface CanDelayRetries
{
    public function delay(int $milliseconds): void;
}
