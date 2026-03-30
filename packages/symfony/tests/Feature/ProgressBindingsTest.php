<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted as AgentCtrlExecutionStarted;
use Cognesy\AgentCtrl\Event\StreamChunkProcessed;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;
use Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressStatus;
use Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressUpdate;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

it('projects raw runtime events onto the dedicated progress bus', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $progress = $app->service(CanHandleProgressUpdates::class);
            $updates = [];

            $progress->wiretap(static function (object $update) use (&$updates): void {
                if ($update instanceof RuntimeProgressUpdate) {
                    $updates[] = $update;
                }
            });

            $events->dispatch(new AgentExecutionStarted(
                agentId: 'agent-progress',
                executionId: 'exec-progress',
                parentAgentId: null,
                messageCount: 1,
                availableTools: 0,
            ));
            $events->dispatch(new AgentStepCompleted(
                agentId: 'agent-progress',
                executionId: 'exec-progress',
                parentAgentId: null,
                stepNumber: 1,
                hasToolCalls: false,
                errorCount: 0,
                errorMessages: '',
                usage: new InferenceUsage(inputTokens: 3, outputTokens: 5),
                finishReason: InferenceFinishReason::Stop,
                startedAt: new DateTimeImmutable('-1 second'),
                durationMs: 12.5,
            ));
            $events->dispatch(new AgentCtrlExecutionStarted(
                agentType: AgentType::Codex,
                executionId: AgentCtrlExecutionId::fromString('agentctrl-exec-1'),
                prompt: 'Inspect the project',
                model: 'codex',
            ));
            $events->dispatch(new StreamChunkProcessed(
                agentType: AgentType::Codex,
                executionId: AgentCtrlExecutionId::fromString('agentctrl-exec-1'),
                chunkNumber: 2,
                chunkSize: 64,
                contentType: 'text/event-stream',
                processingDurationMs: 4.2,
            ));
            $events->dispatch(new DebugStreamChunkReceived(['chunk' => 'delta']));
            $events->dispatch(new AgentExecutionCompleted(
                agentId: 'agent-progress',
                executionId: 'exec-progress',
                parentAgentId: null,
                status: ExecutionStatus::Completed,
                totalSteps: 1,
                totalUsage: new InferenceUsage(inputTokens: 3, outputTokens: 5),
                errors: null,
            ));

            expect($updates)->toHaveCount(6)
                ->and($updates[0]->status)->toBe(RuntimeProgressStatus::Started)
                ->and($updates[0]->source)->toBe('agents')
                ->and($updates[0]->operationId)->toBe('exec-progress')
                ->and($updates[1]->status)->toBe(RuntimeProgressStatus::Progress)
                ->and($updates[1]->source)->toBe('agents')
                ->and($updates[2]->status)->toBe(RuntimeProgressStatus::Started)
                ->and($updates[2]->source)->toBe('agent_ctrl')
                ->and($updates[3]->status)->toBe(RuntimeProgressStatus::Stream)
                ->and($updates[3]->source)->toBe('agent_ctrl')
                ->and($updates[4]->status)->toBe(RuntimeProgressStatus::Stream)
                ->and($updates[4]->source)->toBe('http')
                ->and($updates[5]->status)->toBe(RuntimeProgressStatus::Completed)
                ->and($updates[5]->source)->toBe('agents');
        },
        instructorConfig: progressTestConfig(),
    );
});

it('prints projected progress updates through the optional cli observer', function (): void {
    ob_start();

    try {
        SymfonyTestApp::using(
            callback: static function (SymfonyTestApp $app): void {
                $events = $app->service(CanHandleEvents::class);

                $events->dispatch(new AgentExecutionStarted(
                    agentId: 'agent-cli',
                    executionId: 'exec-cli',
                    parentAgentId: null,
                    messageCount: 1,
                    availableTools: 0,
                ));
                $events->dispatch(new AgentExecutionCompleted(
                    agentId: 'agent-cli',
                    executionId: 'exec-cli',
                    parentAgentId: null,
                    status: ExecutionStatus::Completed,
                    totalSteps: 1,
                    totalUsage: InferenceUsage::none(),
                    errors: null,
                ));
            },
            instructorConfig: progressTestConfig([
                'delivery' => [
                    'cli' => [
                        'enabled' => true,
                        'use_colors' => false,
                        'show_timestamps' => false,
                    ],
                ],
            ]),
        );

        $output = ob_get_clean();
    } catch (Throwable $throwable) {
        ob_end_clean();
        throw $throwable;
    }

    expect($output)->toContain('agents:exec-cli [RUN ]')
        ->and($output)->toContain('execution started')
        ->and($output)->toContain('agents:exec-cli [DONE]')
        ->and($output)->toContain('completed - 1 steps, 0 tokens');
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function progressTestConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
    ], $overrides);
}
