<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Cognesy\Tell\Contracts\CanObserveTellResources;
use Cognesy\Tell\TellResourceEvent;

final readonly class NullTellResourceObserver implements CanObserveTellResources
{
    public function observe(TellResourceEvent $event): void {}
}
