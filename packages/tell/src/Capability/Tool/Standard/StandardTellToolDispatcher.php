<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Tool\Standard;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Execution\CanExecuteTellRuntime;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;

/** Public contract adapter over the same controlled tool path used by Tell SDK. */
final readonly class StandardTellToolDispatcher implements CanDispatchTellTool
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanExecuteTellRuntime $runtime,
        private string $directory,
        private ?CanProvideCancellationSignal $hostCancellation = null,
    ) {}

    #[\Override]
    public function dispatch(
        TellToolRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellToolResult {
        return (new TellToolDispatcher(
            $this->agents,
            $this->runtime,
            $cancellation ?? $this->hostCancellation,
        ))->dispatch(TellToolRequest::fromRequest(
            $request->asRequest($this->directory),
            $request->name,
            $request->arguments,
        ));
    }
}
