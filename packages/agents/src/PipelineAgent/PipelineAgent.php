<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use LogicException;
use Throwable;

/**
 * Pipeline-based agent implementation.
 *
 * This agent delegates ALL lifecycle behavior to an ExecutionPipeline,
 * keeping the core orchestration loop minimal and focused.
 *
 * Implements CanControlAgentLoop for compatibility with existing code.
 */
final class PipelineAgent implements CanControlAgentLoop
{
    public function __construct(
        private readonly CanUseTools $driver,
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly ExecutionPipeline $pipeline,
    ) {}

    #[\Override]
    public function execute(AgentState $state): AgentState
    {
        $finalState = $state;
        foreach ($this->iterate($state) as $stepState) {
            $finalState = $stepState;
        }
        return $finalState;
    }

    #[\Override]
    public function iterate(AgentState $state): iterable
    {
        $ctx = new ExecutionContext(
            driver: $this->driver,
            tools: $this->tools,
            toolExecutor: $this->toolExecutor,
        );

        $state = $this->pipeline->beforeExecution($state, $ctx);

        while ($this->pipeline->shouldContinue($state, $ctx)) {
            try {
                $state = $this->pipeline->beforeStep($state, $ctx);
                $state = $this->pipeline->executeStep($state, $ctx);
                $state = $this->pipeline->afterStep($state, $ctx);
            } catch (LogicException $error) {
                throw $error; // Programming errors propagate
            } catch (Throwable $error) {
                $state = $this->pipeline->onError($error, $state, $ctx);
            }
            yield $state;
        }

        yield $this->pipeline->afterExecution($state, $ctx);
    }

    // ACCESSORS ////////////////////////////////////////////

    public function driver(): CanUseTools
    {
        return $this->driver;
    }

    public function tools(): Tools
    {
        return $this->tools;
    }

    public function toolExecutor(): CanExecuteToolCalls
    {
        return $this->toolExecutor;
    }

    public function pipeline(): ExecutionPipeline
    {
        return $this->pipeline;
    }
}
