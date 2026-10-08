<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

interface CanDelayBatchRead
{
    public function wait(int $milliseconds): void;
}
