<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Protocol\TellAgentProtocolRequest;

interface CanRunTellProtocol
{
    public function run(
        TellAgentProtocolRequest $request,
        CanWriteTellProtocolFrames $frames,
        ?CanProvideCancellationSignal $cancellation = null,
    ): int;
}
