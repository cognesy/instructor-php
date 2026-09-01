<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

use Cognesy\Tell\Core\Contract\Execution\CanExecuteTellRuntime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
interface CanCreateTellRuntime
{
    public function create(?CanProvideCancellationSignal $cancellation = null): CanExecuteTellRuntime;
}
