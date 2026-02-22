<?php declare(strict_types=1);

namespace Cognesy\Agents\Events\Support;

use Cognesy\Events\Support\ConsoleEventPrinter;

final class AgentEventConsoleObserver
{
    private readonly ConsoleEventPrinter $printer;
    private readonly AgentEventConsoleFormatter $formatter;

    public function __construct(
        bool $useColors = true,
        bool $showTimestamps = true,
        bool $showAgentIds = true,
        bool $showContinuation = true,
        bool $showToolArgs = true,
        bool $showInference = true,
        bool $showSubagents = true,
        bool $showHooks = false,
        bool $showFailures = true,
        int $maxArgLength = 100,
    ) {
        $this->printer = new ConsoleEventPrinter(
            useColors: $useColors,
            showTimestamps: $showTimestamps,
        );

        $this->formatter = new AgentEventConsoleFormatter(
            showAgentIds: $showAgentIds,
            showContinuation: $showContinuation,
            showToolArgs: $showToolArgs,
            showInference: $showInference,
            showSubagents: $showSubagents,
            showHooks: $showHooks,
            showFailures: $showFailures,
            maxArgLength: $maxArgLength,
        );
    }

    public function wiretap(): callable
    {
        return $this->printer->wiretap($this->formatter);
    }
}
