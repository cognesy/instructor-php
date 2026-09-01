<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Protocol\OneRun\Contract;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Data\TellProtocolRequest;

interface CanRunTellProtocol
{
    public function run(
        TellProtocolRequest $request,
        CanWriteTellProtocolFrames $frames,
        ?CanProvideCancellationSignal $cancellation = null,
    ): int;
}
