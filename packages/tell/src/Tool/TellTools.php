<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tool;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;
use Cognesy\Tell\Runtime\TellAgentFactory;
use LogicException;

/** Direct, model-free tool calls resolved from the same Tell configuration as agent work. */
final readonly class TellTools
{
    public function __construct(
        private ?TellAgentFactory $agents,
        private string $directory,
        private ?CanProvideCancellationSignal $cancellation = null,
        private ?CanDispatchTellTool $dispatcher = null,
    ) {}

    public static function controlled(
        CanDispatchTellTool $dispatcher,
        ?CanProvideCancellationSignal $cancellation = null,
    ): self {
        return new self(null, '', $cancellation, $dispatcher);
    }

    public function dispatch(TellToolRequest $request): TellToolResult {
        if ($this->dispatcher !== null) {
            return $this->dispatcher->dispatch($request, $this->cancellation);
        }
        if ($this->agents === null) {
            throw new LogicException('Tell tools require an agent factory or controlled dispatcher.');
        }
        $result = (new TellToolDispatcher($this->agents, $this->cancellation))->dispatch(
            new TellOptions(
                prompt: 'Direct tool invocation.',
                agent: $request->agent,
                connection: $request->connection,
                model: $request->model,
                dsn: $request->dsn,
                branch: $request->branch,
                directory: $this->directory,
                tools: array_values($request->tools),
                answers: new TellAnswerQueue(),
                maxSteps: $request->maxSteps,
                connectionExplicit: $request->connectionExplicit,
                modelExplicit: $request->modelExplicit,
                toolsExplicit: $request->toolsExplicit,
                policy: $request->policy,
            ),
            $request->name,
            $request->arguments,
        );

        return new TellToolResult(
            tool: $result['tool'],
            success: $result['success'],
            operation: $result['operation'],
            invokedAs: $result['invokedAs'],
            data: $result['data'],
            error: $result['error'],
            truncated: $result['truncated'],
            partial: $result['partial'],
            durationClass: $result['durationClass'],
            effect: $result['effect'],
        );
    }
}
