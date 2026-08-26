<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\TellResourceEvent;

interface CanObserveTellResources
{
    public function observe(TellResourceEvent $event): void;
}
