<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;

interface CanAcceptEventHandler
{
    public function withEventHandler(CanHandleEvents $events): static;
}
