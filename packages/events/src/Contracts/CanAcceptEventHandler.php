<?php declare(strict_types=1);

namespace Cognesy\Events\Contracts;

interface CanAcceptEventHandler
{
    public function withEventHandler(CanHandleEvents $events): static;
}
