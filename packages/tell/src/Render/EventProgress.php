<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class EventProgress
{
    public function __construct(
        private OutputInterface $stderr,
        private bool $verbose = false,
        private bool $quiet = false,
    ) {}

    public function attach(AgentLoop $loop): void
    {
        if ($this->quiet) {
            return;
        }
        $loop->onEvent(InferenceRequestStarted::class, function (InferenceRequestStarted $event): void {
            $this->stderr->writeln("[inference.start] step={$event->stepNumber}");
        });
        if (! $this->verbose) {
            return;
        }
        $loop->onEvent(AgentStepStarted::class, function (AgentStepStarted $event): void {
            $this->stderr->writeln("[step.start] step={$event->stepNumber}");
        });
        $loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event): void {
            $this->stderr->writeln("[step.complete] step={$event->stepNumber}");
        });
        $loop->onEvent(ToolCallStarted::class, function (ToolCallStarted $event): void {
            $this->stderr->writeln("[tool.start] name={$event->tool}");
        });
        $loop->onEvent(ToolCallCompleted::class, function (ToolCallCompleted $event): void {
            $status = match ($event->success) {
                true => 'ok',
                false => 'failed',
            };
            $this->stderr->writeln("[tool.complete] name={$event->tool} status={$status}");
        });
    }
}
