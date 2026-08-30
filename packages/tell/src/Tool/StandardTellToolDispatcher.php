<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tool;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;
use Cognesy\Tell\Runtime\TellRuntime;

/** Public contract adapter over the same controlled tool path used by Tell SDK. */
final readonly class StandardTellToolDispatcher implements CanDispatchTellTool
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private TellRuntime $runtime,
        private string $directory,
        private ?CanProvideCancellationSignal $hostCancellation = null,
    ) {}

    #[\Override]
    public function dispatch(
        TellToolRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellToolResult {
        return (new TellTools(
            $this->agents,
            $this->directory,
            $cancellation ?? $this->hostCancellation,
            runtime: $this->runtime,
        ))->dispatch($request);
    }
}
