<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Tool;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;

/** Direct, model-free tool calls resolved from the same Tell configuration as agent work. */
final readonly class TellTools
{
    public function __construct(
        private CanDispatchTellTool $dispatcher,
        private ?CanProvideCancellationSignal $cancellation = null,
    ) {}

    public function dispatch(TellToolRequest $request): TellToolResult {
        return $this->dispatcher->dispatch($request, $this->cancellation);
    }
}
