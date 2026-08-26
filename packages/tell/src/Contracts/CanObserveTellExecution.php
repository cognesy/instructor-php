<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Contracts\Data\TellEventEnvelope;

interface CanObserveTellExecution
{
    public function observe(TellEventEnvelope $event): void;
}
