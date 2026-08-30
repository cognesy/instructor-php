<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Runtime\TellRuntime;

interface CanCreateTellRuntime
{
    public function create(?CanProvideCancellationSignal $cancellation = null): TellRuntime;
}
