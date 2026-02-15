<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Data;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Events\Contracts\CanHandleEvents;

final readonly class DeferredToolContext
{
    public function __construct(
        private Tools $tools,
        private CanUseTools $toolUseDriver,
        private CanHandleEvents $events,
    ) {}

    public function tools(): Tools {
        return $this->tools;
    }

    public function toolUseDriver(): CanUseTools {
        return $this->toolUseDriver;
    }

    public function events(): CanHandleEvents {
        return $this->events;
    }
}

