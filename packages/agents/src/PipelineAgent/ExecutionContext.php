<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Exceptions\AgentException;
use DateTimeImmutable;

/**
 * Carries contextual data between pipeline handlers during execution.
 *
 * This is a mutable context object that handlers can read from and write to.
 * It provides access to the agent's dependencies and tracks execution timing.
 */
final class ExecutionContext
{
    public ?DateTimeImmutable $executionStartedAt = null;
    public ?DateTimeImmutable $stepStartedAt = null;
    public int $stepNumber = 0;
    public ?AgentException $lastException = null;

    public function __construct(
        public readonly CanUseTools $driver,
        public readonly Tools $tools,
        public readonly CanExecuteToolCalls $toolExecutor,
    ) {}

    public function beginExecution(): void
    {
        $this->executionStartedAt = new DateTimeImmutable();
    }

    public function beginStep(): void
    {
        $this->stepStartedAt = new DateTimeImmutable();
        $this->stepNumber++;
    }
}
