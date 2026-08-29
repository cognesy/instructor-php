<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tool;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;
use Cognesy\Tell\Runtime\TellAgentFactory;

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
