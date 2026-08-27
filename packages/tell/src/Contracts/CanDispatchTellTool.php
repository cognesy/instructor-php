<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Tool\TellToolRequest;
use Cognesy\Tell\Tool\TellToolResult;

interface CanDispatchTellTool
{
    public function dispatch(
        TellToolRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellToolResult;
}
