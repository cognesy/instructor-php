<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\ExecutionHistory;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\ExecutionHistory\ArrayExecutionStore;
use Cognesy\Agents\Capability\ExecutionHistory\ExecutionHistoryHook;
use Cognesy\Agents\Capability\ExecutionHistory\ExecutionSummary;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentId;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionId;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\MockTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

function toolCallResponse(string $name, array $args): InferenceResponse {
    $toolCall = ToolCall::fromArray([
        'id' => 'call_' . uniqid(),
        'name' => $name,
        'arguments' => json_encode($args),
    ]);
    return new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall));
}

function finalResponse(string $content): InferenceResponse {
    return new InferenceResponse(content: $content);
}

function makeLoop(
    array $inferenceResponses,
    ?ExecutionHistoryHook $historyHook = null,
    Tools $extraTools = new Tools(),
): AgentLoop {
    $events = new EventDispatcher();
    $tools = $extraTools;

    $hooksBuilder = new HookStack(new RegisteredHooks(), $events);
    if ($historyHook !== null) {
        $hooksBuilder = $hooksBuilder->with(
            hook: $historyHook,
            triggerTypes: HookTriggers::with(HookTrigger::AfterExecution),
            priority: -1000,
            name: 'execution_history',
        );
    }

    $fakeDriver = new FakeInferenceRequestDriver($inferenceResponses);
    $llm = LLMProvider::new()->withDriver($fakeDriver);
    $driver = new ToolCallingDriver(
        inference: InferenceRuntime::fromProvider($llm, events: $events),
        llm: $llm,
        events: $events,
    );

    $executor = new ToolExecutor($tools, events: $events, interceptor: $hooksBuilder);

    return new AgentLoop(
        tools: $tools,
        toolExecutor: $executor,
        driver: $driver,
        events: $events,
        interceptor: $hooksBuilder,
    );
}

describe('Execution Counter', function () {

    it('starts at 0 for fresh state', function () {
        $state = AgentState::empty();
        expect($state->executionCount())->toBe(0);
    });

    it('increments to 1 after first execution', function () {
        $loop = makeLoop(
            inferenceResponses: [finalResponse('Hello')],
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $finalState = $loop->execute($state);

        expect($finalState->executionCount())->toBe(1);
    });

    it('increments across multiple executions', function () {
        $loop = makeLoop(
            inferenceResponses: [
                finalResponse('First'),
                finalResponse('Second'),
                finalResponse('Third'),
            ],
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));

        $state = $loop->execute($state);
        expect($state->executionCount())->toBe(1);

        $state = $loop->execute($state);
        expect($state->executionCount())->toBe(2);

        $state = $loop->execute($state);
        expect($state->executionCount())->toBe(3);
    });

    it('survives serialization round-trip', function () {
        $state = AgentState::empty();
        $state = $state->with(executionCount: 5);

        $serialized = $state->toArray();
        $restored = AgentState::fromArray($serialized);

        expect($restored->executionCount())->toBe(5);
    });

    it('is preserved across forNextExecution', function () {
        $loop = makeLoop(
            inferenceResponses: [finalResponse('Done')],
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $state = $loop->execute($state);

        expect($state->executionCount())->toBe(1);

        // forNextExecution resets execution state but preserves counter
        $next = $state->forNextExecution();
        expect($next->executionCount())->toBe(1);
        expect($next->execution())->toBeNull();
    });
});

describe('Execution History', function () {

    it('records execution summary after each execution', function () {
        $store = new ArrayExecutionStore();
        $historyHook = new ExecutionHistoryHook(store: $store);

        $loop = makeLoop(
            inferenceResponses: [finalResponse('Done')],
            historyHook: $historyHook,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $state = $loop->execute($state);

        expect($store->count($state->agentId()))->toBe(1);

        $summary = $store->last($state->agentId());
        expect($summary)->toBeInstanceOf(ExecutionSummary::class);
        expect($summary->executionNumber)->toBe(1);
        expect($summary->stepCount)->toBe(1);
        expect($summary->status)->toBe(ExecutionStatus::Completed);
    });

    it('records multiple executions with incrementing execution number', function () {
        $store = new ArrayExecutionStore();
        $historyHook = new ExecutionHistoryHook(store: $store);

        $loop = makeLoop(
            inferenceResponses: [
                finalResponse('First'),
                finalResponse('Second'),
            ],
            historyHook: $historyHook,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));

        $state = $loop->execute($state);
        $state = $loop->execute($state);

        $history = $store->all($state->agentId());
        expect($history)->toHaveCount(2);
        expect($history[0]->executionNumber)->toBe(1);
        expect($history[1]->executionNumber)->toBe(2);
    });

    it('captures step count and usage in summary', function () {
        $searchTool = MockTool::returning('search', 'Search tool', 'found');
        $store = new ArrayExecutionStore();
        $historyHook = new ExecutionHistoryHook(store: $store);

        $loop = makeLoop(
            inferenceResponses: [
                toolCallResponse('search', ['q' => 'test']),
                toolCallResponse('search', ['q' => 'more']),
                finalResponse('All done'),
            ],
            historyHook: $historyHook,
            extraTools: new Tools($searchTool),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Search'));
        $state = $loop->execute($state);

        $summary = $store->last($state->agentId());
        expect($summary->stepCount)->toBe(3);
        expect($summary->status)->toBe(ExecutionStatus::Completed);
        expect($summary->executionId)->toBeInstanceOf(ExecutionId::class);
        expect($summary->duration)->toBeGreaterThanOrEqual(0.0);
    });

    it('returns null for unknown agent', function () {
        $store = new ArrayExecutionStore();
        $unknown = AgentId::generate();
        expect($store->last($unknown))->toBeNull();
        expect($store->count($unknown))->toBe(0);
        expect($store->all($unknown))->toBe([]);
    });

    it('summary serializes to array', function () {
        $store = new ArrayExecutionStore();
        $historyHook = new ExecutionHistoryHook(store: $store);

        $loop = makeLoop(
            inferenceResponses: [finalResponse('Done')],
            historyHook: $historyHook,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $loop->execute($state);

        $summary = $store->last($state->agentId());
        $array = $summary->toArray();

        expect($array)->toHaveKeys([
            'executionId', 'executionNumber', 'status', 'stepCount',
            'usage', 'duration', 'startedAt', 'completedAt',
            'stopReason', 'stopMessage', 'errorCount',
        ]);
        expect($array['executionNumber'])->toBe(1);
        expect($array['status'])->toBe('completed');
    });
});
