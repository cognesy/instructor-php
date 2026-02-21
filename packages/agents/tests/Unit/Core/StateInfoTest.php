<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use DateTimeImmutable;

it('touches updatedAt on state changes while keeping createdAt', function () {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-01T01:00:00+00:00');
    $state = new AgentState(createdAt: $createdAt, updatedAt: $updatedAt);

    $next = $state->withMessages(Messages::fromString('ping'));

    expect($next->createdAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-01T00:00:00+00:00')
        ->and($next->updatedAt()->getTimestamp())->toBeGreaterThan($state->updatedAt()->getTimestamp());
});

it('serializes and deserializes state timestamps', function () {
    $createdAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T01:00:00+00:00');
    $state = new AgentState(createdAt: $createdAt, updatedAt: $updatedAt);

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->createdAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-02T00:00:00+00:00')
        ->and($restored->updatedAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-02T01:00:00+00:00');
});

it('round-trips llm config in state serialization', function () {
    $config = new LLMConfig(
        model: 'gpt-4o-mini',
        contextLength: 128000,
    );

    $state = AgentState::empty()->withLLMConfig($config);
    $restored = AgentState::fromArray($state->toArray());

    expect($restored->llmConfig())->not->toBeNull()
        ->and($restored->llmConfig()?->model)->toBe('gpt-4o-mini')
        ->and($restored->llmConfig()?->contextLength)->toBe(128000);
});

it('preserves null execution on round-trip serialization', function () {
    $state = AgentState::empty();

    expect($state->execution())->toBeNull();

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->execution())->toBeNull();
});

it('preserves execution state on round-trip serialization', function () {
    $state = AgentState::empty()->withExecutionContinued();

    expect($state->execution())->not->toBeNull();

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->execution())->not->toBeNull()
        ->and($restored->execution()->executionId()->toString())->toBe($state->execution()->executionId()->toString());
});

it('preserves stepCount unchanged after round-trip without current step', function () {
    $state = AgentState::empty()->withExecutionContinued();

    expect($state->stepCount())->toBe(0)
        ->and($state->currentStep())->toBeNull();

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->stepCount())->toBe(0)
        ->and($restored->currentStep())->toBeNull();
});

it('handles null completedAt in execution state round-trip', function () {
    $state = AgentState::empty()->withExecutionContinued();
    $array = $state->toArray();

    expect($array['execution']['completedAt'])->toBeNull();

    $restored = AgentState::fromArray($array);
    $restoredArray = $restored->toArray();

    expect($restoredArray['execution']['completedAt'])->toBeNull();
});

it('handles malformed datetime strings gracefully', function () {
    $data = [
        'executionId' => '00000000-0000-4000-8000-000000000001',
        'status' => 'pending',
        'startedAt' => 'not-a-valid-date',
        'completedAt' => 'also-invalid',
        'stepExecutions' => [],
        'continuation' => [],
    ];

    $execution = \Cognesy\Agents\Data\ExecutionState::fromArray($data);
    $array = $execution->toArray();

    expect($execution->executionId()->toString())->toBe('00000000-0000-4000-8000-000000000001')
        ->and($array['startedAt'])->toBeString()
        ->and($array['completedAt'])->toBeNull();
});

it('handles empty string datetime values gracefully', function () {
    $data = [
        'executionId' => '00000000-0000-4000-8000-000000000001',
        'status' => 'pending',
        'startedAt' => '',
        'completedAt' => '',
        'stepExecutions' => [],
        'continuation' => [],
    ];

    $execution = \Cognesy\Agents\Data\ExecutionState::fromArray($data);
    $array = $execution->toArray();

    expect($array['startedAt'])->toBeString()
        ->and($array['completedAt'])->toBeNull();
});

it('calculates totalDuration with sub-second precision', function () {
    $execution = \Cognesy\Agents\Data\ExecutionState::fresh();

    usleep(50000); // 50ms

    $completed = $execution->completed();
    $duration = $completed->totalDuration();

    expect($duration)->toBeGreaterThan(0.04)
        ->and($duration)->toBeLessThan(0.2);
});

it('exposes final response when final step has content', function () {
    $step = new AgentStep(
        outputMessages: Messages::fromString('Final answer'),
    );

    $state = AgentState::empty()
        ->withCurrentStep($step)
        ->withExecutionCompleted();

    expect($state->hasFinalResponse())->toBeTrue()
        ->and(trim($state->finalResponse()->toString()))->toBe('Final answer');
});

it('returns no final response when there are no steps', function () {
    $state = AgentState::empty();

    expect($state->hasFinalResponse())->toBeFalse()
        ->and($state->finalResponse()->isEmpty())->toBeTrue();
});

it('returns no final response for a tool-execution step', function () {
    $toolCall = ToolCall::fromArray([
        'id' => 'call_1',
        'name' => 'lookup',
        'arguments' => json_encode(['q' => 'weather']),
    ]);

    $step = new AgentStep(
        outputMessages: Messages::fromString('Tool trace'),
        inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
    );

    $state = AgentState::empty()
        ->withCurrentStep($step)
        ->withExecutionCompleted();

    expect($state->hasFinalResponse())->toBeFalse()
        ->and($state->finalResponse()->isEmpty())->toBeTrue();
});

it('returns no final response for empty final output', function () {
    $step = new AgentStep(
        outputMessages: Messages::fromString(''),
    );

    $state = AgentState::empty()
        ->withCurrentStep($step)
        ->withExecutionCompleted();

    expect($state->hasFinalResponse())->toBeFalse()
        ->and($state->finalResponse()->isEmpty())->toBeTrue();
});

it('returns final response in multi-step execution before execution completion', function () {
    $toolCall = ToolCall::fromArray([
        'id' => 'call_1',
        'name' => 'lookup',
        'arguments' => json_encode(['q' => 'weather']),
    ]);

    $toolStep = new AgentStep(
        outputMessages: Messages::fromString('tool trace'),
        inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
    );

    $finalStep = new AgentStep(
        outputMessages: Messages::fromString('Second step final answer'),
    );

    $state = AgentState::empty()
        ->withCurrentStep($toolStep)
        ->withCurrentStepCompleted()
        ->withCurrentStep($finalStep);

    expect($state->stepCount())->toBe(2)
        ->and($state->hasFinalResponse())->toBeTrue()
        ->and(trim($state->finalResponse()->toString()))->toBe('Second step final answer');
});

it('returns final response in multi-step execution after execution completion', function () {
    $toolCall = ToolCall::fromArray([
        'id' => 'call_1',
        'name' => 'lookup',
        'arguments' => json_encode(['q' => 'weather']),
    ]);

    $toolStep = new AgentStep(
        outputMessages: Messages::fromString('tool trace'),
        inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
    );

    $finalStep = new AgentStep(
        outputMessages: Messages::fromString('Completed final answer'),
    );

    $state = AgentState::empty()
        ->withCurrentStep($toolStep)
        ->withCurrentStepCompleted()
        ->withCurrentStep($finalStep)
        ->withExecutionCompleted();

    expect($state->stepCount())->toBe(2)
        ->and($state->hasFinalResponse())->toBeTrue()
        ->and(trim($state->finalResponse()->toString()))->toBe('Completed final answer');
});

it('returns no final response during second execution when current turn is incomplete', function () {
    $firstTurn = AgentState::empty()
        ->withCurrentStep(new AgentStep(outputMessages: Messages::fromString('Turn 1 answer')))
        ->withExecutionCompleted();

    $toolCall = ToolCall::fromArray([
        'id' => 'call_2',
        'name' => 'search',
        'arguments' => json_encode(['q' => 'turn2']),
    ]);

    $secondTurnInProgress = $firstTurn
        ->forNextExecution()
        ->withUserMessage('Turn 2 question')
        ->withCurrentStep(new AgentStep(
            outputMessages: Messages::fromString('turn 2 tool trace'),
            inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
        ));

    expect($secondTurnInProgress->stepCount())->toBe(1)
        ->and($secondTurnInProgress->hasFinalResponse())->toBeFalse()
        ->and($secondTurnInProgress->finalResponse()->isEmpty())->toBeTrue();
});

it('returns final response from the latest turn in multi-execution flow', function () {
    $firstTurn = AgentState::empty()
        ->withCurrentStep(new AgentStep(outputMessages: Messages::fromString('Turn 1 answer')))
        ->withExecutionCompleted();

    $secondTurnCompleted = $firstTurn
        ->forNextExecution()
        ->withUserMessage('Turn 2 question')
        ->withCurrentStep(new AgentStep(outputMessages: Messages::fromString('Turn 2 answer')))
        ->withExecutionCompleted();

    expect($secondTurnCompleted->stepCount())->toBe(1)
        ->and($secondTurnCompleted->hasFinalResponse())->toBeTrue()
        ->and(trim($secondTurnCompleted->finalResponse()->toString()))->toBe('Turn 2 answer');
});
