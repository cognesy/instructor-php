<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Observation;

use Cognesy\Tell\Data\TellEventEnvelope;

interface CanObserveTellExecution
{
    public function observe(TellEventEnvelope $event): void;
}
