<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\Data\TellEventEnvelope;

final readonly class NullTellObserver implements CanObserveTellExecution
{
    public function observe(TellEventEnvelope $event): void {}
}
