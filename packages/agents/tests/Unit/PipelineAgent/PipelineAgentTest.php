<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\PipelineAgent;

use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Agents\Core\Continuation\Criteria\ToolCallPresenceCheck;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\PipelineAgent\PipelineAgent;
use Cognesy\Agents\PipelineAgent\PipelineBuilder;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use PHPUnit\Framework\TestCase;

final class PipelineAgentTest extends TestCase
{
    private function createDefaultCriteria(): ContinuationCriteria
    {
        return ContinuationCriteria::from(
            // Stop when finish reason indicates completion
            new FinishReasonCheck(
                [InferenceFinishReason::Stop],
                static fn($state) => $state->currentStep()?->finishReason()
            ),
            // Continue if there are tool calls or no step yet
            new ToolCallPresenceCheck(
                static fn($state) => $state->currentStep() === null
                    || $state->currentStep()->hasToolCalls()
            ),
        );
    }

    public function test_pipeline_agent_executes_single_step(): void
    {
        // Create a mock driver that returns a step with "stop" finish reason
        $driver = $this->createMock(CanUseTools::class);
        $driver->expects($this->once())
            ->method('useTools')
            ->willReturn(new AgentStep(
                inferenceResponse: new InferenceResponse(
                    content: 'Hello',
                    finishReason: 'stop',
                    usage: new Usage(10, 20),
                ),
            ));

        $tools = new Tools();
        $toolExecutor = new ToolExecutor($tools);
        $criteria = $this->createDefaultCriteria();

        $pipeline = PipelineBuilder::minimal($criteria)->build();

        $agent = new PipelineAgent(
            driver: $driver,
            tools: $tools,
            toolExecutor: $toolExecutor,
            pipeline: $pipeline,
        );

        $initialState = AgentState::empty();
        $finalState = $agent->execute($initialState);

        $this->assertSame(1, $finalState->stepCount());
        $this->assertSame('completed', $finalState->status()->value);
    }

    public function test_pipeline_agent_implements_can_control_agent_loop(): void
    {
        $driver = $this->createMock(CanUseTools::class);
        $tools = new Tools();
        $toolExecutor = new ToolExecutor($tools);
        $criteria = $this->createDefaultCriteria();
        $pipeline = PipelineBuilder::minimal($criteria)->build();

        $agent = new PipelineAgent(
            driver: $driver,
            tools: $tools,
            toolExecutor: $toolExecutor,
            pipeline: $pipeline,
        );

        $this->assertInstanceOf(CanControlAgentLoop::class, $agent);
    }

    public function test_pipeline_agent_iterates_yielding_states(): void
    {
        // First, verify the mock works
        $stepCount = 0;
        $driver = $this->createMock(CanUseTools::class);
        $driver->expects($this->exactly(2))
            ->method('useTools')
            ->willReturnCallback(function() use (&$stepCount) {
                $stepCount++;
                // Stop after 2 steps
                $finishReason = $stepCount >= 2 ? 'stop' : 'tool_calls';
                $toolCalls = $stepCount < 2
                    ? new ToolCalls(new ToolCall('test_tool', ['arg' => 'value'], 'call_1'))
                    : null;

                return new AgentStep(
                    inferenceResponse: new InferenceResponse(
                        content: "Step $stepCount",
                        finishReason: $finishReason,
                        toolCalls: $toolCalls,
                        usage: new Usage(10, 20),
                    ),
                );
            });

        $tools = new Tools();
        $toolExecutor = new ToolExecutor($tools);
        $criteria = $this->createDefaultCriteria();
        $pipeline = PipelineBuilder::minimal($criteria)->build();

        $agent = new PipelineAgent(
            driver: $driver,
            tools: $tools,
            toolExecutor: $toolExecutor,
            pipeline: $pipeline,
        );

        $states = [];
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $states[] = $state;
        }

        // Verify basic behavior
        $this->assertGreaterThanOrEqual(2, count($states));
        $this->assertSame(2, end($states)->stepCount());
    }
}
