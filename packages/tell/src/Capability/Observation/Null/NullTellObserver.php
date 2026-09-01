<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\Null;

use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Data\TellEventEnvelope;

final readonly class NullTellObserver implements CanObserveTellExecution
{
    #[\Override]
    public function observe(TellEventEnvelope $event): void {}
}
