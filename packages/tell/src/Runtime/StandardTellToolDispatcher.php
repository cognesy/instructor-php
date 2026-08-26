<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\TellToolRequest;
use Cognesy\Tell\TellToolResult;
use Cognesy\Tell\TellTools;

/** Public contract adapter over the same controlled tool path used by Tell SDK. */
final readonly class StandardTellToolDispatcher implements CanDispatchTellTool
{
    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
        private ?CanProvideCancellationSignal $hostCancellation = null,
    ) {}

    public function dispatch(
        TellToolRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellToolResult {
        return (new TellTools(
            $this->agents,
            $this->directory,
            $cancellation ?? $this->hostCancellation,
        ))->dispatch($request);
    }
}
