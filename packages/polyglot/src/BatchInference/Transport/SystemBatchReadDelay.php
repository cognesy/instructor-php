<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use Cognesy\Polyglot\BatchInference\Contracts\CanDelayBatchRead;

final readonly class SystemBatchReadDelay implements CanDelayBatchRead
{
    #[\Override]
    public function wait(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
