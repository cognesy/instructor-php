<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;

interface CanDispatchTellTool
{
    public function dispatch(
        TellToolRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellToolResult;
}
