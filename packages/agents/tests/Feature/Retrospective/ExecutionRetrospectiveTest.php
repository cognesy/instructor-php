<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Retrospective;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Retrospective\ExecutionRetrospectiveHook;
use Cognesy\Agents\Capability\Retrospective\ExecutionRetrospectiveTool;
use Cognesy\Agents\Capability\Retrospective\RetrospectivePolicy;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Enums\AgentStepType;
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

function makeRetrospectiveLoop(
    array $inferenceResponses,
    ?RetrospectivePolicy $policy = null,
    Tools $extraTools = new Tools(),
): AgentLoop {
    $policy = $policy ?? new RetrospectivePolicy();
    $events = new EventDispatcher();

    $retrospectiveTool = new ExecutionRetrospectiveTool();
    $tools = $extraTools->merge(new Tools($retrospectiveTool));

    $hooks = (new HookStack(new RegisteredHooks(), $events))->with(
        hook: new ExecutionRetrospectiveHook(policy: $policy),
        triggerTypes: HookTriggers::with(HookTrigger::BeforeStep, HookTrigger::AfterStep),
        priority: 100,
        name: 'execution_retrospective',
    );

    $fakeDriver = new FakeInferenceRequestDriver($inferenceResponses);
    $llm = LLMProvider::new()->withDriver($fakeDriver);
    $driver = new ToolCallingDriver(
        inference: InferenceRuntime::fromProvider($llm, events: $events),
        llm: $llm,
        events: $events,
    );

    $executor = new ToolExecutor($tools, events: $events, interceptor: $hooks);

    return new AgentLoop(
        tools: $tools,
        toolExecutor: $executor,
        driver: $driver,
        events: $events,
        interceptor: $hooks,
    );
}

describe('Execution Retrospective', function () {

    it('injects checkpoint messages before each step', function () {
        $loop = makeRetrospectiveLoop(
            inferenceResponses: [
                finalResponse('Hello'),
            ],
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $finalState = $loop->execute($state);

        // BeforeStep should have injected CHECKPOINT 0
        $hasCheckpoint = false;
        foreach ($finalState->messages()->each() as $msg) {
            if (str_contains($msg->toString(), '[CHECKPOINT 0]')) {
                $hasCheckpoint = true;
                break;
            }
        }
        expect($hasCheckpoint)->toBeTrue();

        // Checkpoint counter should be incremented
        $checkpointCount = $finalState->metadata()->get(ExecutionRetrospectiveHook::CHECKPOINT_COUNT_KEY, 0);
        expect($checkpointCount)->toBe(1);
    });

    it('rewinds messages to checkpoint and injects guidance', function () {
        $searchTool = MockTool::returning('search', 'Search tool', 'result-A');

        $loop = makeRetrospectiveLoop(
            inferenceResponses: [
                // Step 0: CHECKPOINT 0 injected, then search
                toolCallResponse('search', ['q' => 'first']),
                // Step 1: CHECKPOINT 1 injected, then search
                toolCallResponse('search', ['q' => 'second']),
                // Step 2: CHECKPOINT 2 injected, then retrospective
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 1,
                    'guidance' => 'Skip second search, go straight to answer',
                ]),
                // Step 3: CHECKPOINT 1 injected (counter reset), then final
                finalResponse('Done with guidance'),
            ],
            extraTools: new Tools($searchTool),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Search for info'));
        $finalState = $loop->execute($state);

        // All 4 steps are recorded — execution history is never truncated
        expect($finalState->stepCount())->toBe(4);

        $steps = $finalState->steps()->all();
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution); // search
        expect($steps[1]->stepType())->toBe(AgentStepType::ToolExecution); // search
        expect($steps[2]->stepType())->toBe(AgentStepType::ToolExecution); // retrospective
        expect($steps[3]->stepType())->toBe(AgentStepType::FinalResponse); // final

        // Guidance should be present in messages
        $hasGuidance = false;
        foreach ($finalState->messages()->each() as $msg) {
            if (str_contains($msg->toString(), 'Skip second search')) {
                $hasGuidance = true;
                break;
            }
        }
        expect($hasGuidance)->toBeTrue();

        // Messages from step 1 onward (after CHECKPOINT 1) should be gone
        // Only: original user msg, CHECKPOINT 0, step 0's output, guidance, CHECKPOINT 1, step 3's output
        $hasCheckpoint0 = false;
        $hasCheckpoint2 = false;
        foreach ($finalState->messages()->each() as $msg) {
            if (str_contains($msg->toString(), '[CHECKPOINT 0]')) {
                $hasCheckpoint0 = true;
            }
            if (str_contains($msg->toString(), '[CHECKPOINT 2]')) {
                $hasCheckpoint2 = true;
            }
        }
        expect($hasCheckpoint0)->toBeTrue();
        expect($hasCheckpoint2)->toBeFalse(); // truncated
    });

    it('rewinds to checkpoint 0 and clears all step messages', function () {
        $searchTool = MockTool::returning('search', 'Search tool', 'found');

        $loop = makeRetrospectiveLoop(
            inferenceResponses: [
                // Step 0: CHECKPOINT 0, search
                toolCallResponse('search', ['q' => 'wrong']),
                // Step 1: CHECKPOINT 1, retrospective to checkpoint 0
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 0,
                    'guidance' => 'Start over with better approach',
                ]),
                // Step 2: CHECKPOINT 0 (reset), final
                finalResponse('Fresh start'),
            ],
            extraTools: new Tools($searchTool),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Do something'));
        $finalState = $loop->execute($state);

        // All 3 steps recorded in history
        expect($finalState->stepCount())->toBe(3);

        // Messages should only contain: original user msg, guidance, CHECKPOINT 0, step 2 output
        // CHECKPOINT 0 from step 0 was truncated — a new CHECKPOINT 0 was created for step 2
        $messages = $finalState->messages();
        $hasOriginal = false;
        $hasGuidance = false;
        foreach ($messages->each() as $msg) {
            if (str_contains($msg->toString(), 'Do something')) {
                $hasOriginal = true;
            }
            if (str_contains($msg->toString(), 'Start over with better approach')) {
                $hasGuidance = true;
            }
        }
        expect($hasOriginal)->toBeTrue();
        expect($hasGuidance)->toBeTrue();
    });

    it('respects maxRewinds limit', function () {
        $searchTool = MockTool::returning('search', 'Search tool', 'found');

        $loop = makeRetrospectiveLoop(
            inferenceResponses: [
                toolCallResponse('search', []),                        // step 0
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 0, 'guidance' => 'Try again 1',
                ]),                                                    // step 1: rewind #1
                toolCallResponse('search', []),                        // step 2
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 0, 'guidance' => 'Try again 2',
                ]),                                                    // step 3: rewind #2
                toolCallResponse('search', []),                        // step 4
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 0, 'guidance' => 'This should not rewind',
                ]),                                                    // step 5: ignored (maxRewinds=2)
                finalResponse('Done'),                                 // step 6
            ],
            policy: new RetrospectivePolicy(maxRewinds: 2),
            extraTools: new Tools($searchTool),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Do task'));
        $finalState = $loop->execute($state);

        $rewindCount = $finalState->metadata()->get(ExecutionRetrospectiveHook::REWIND_COUNT_KEY, 0);
        expect($rewindCount)->toBe(2);

        // All steps recorded — nothing truncated from execution history
        expect($finalState->stepCount())->toBe(7);
    });

    it('resets checkpoint counter after rewind', function () {
        $searchTool = MockTool::returning('search', 'Search tool', 'data');

        $loop = makeRetrospectiveLoop(
            inferenceResponses: [
                toolCallResponse('search', ['q' => 'first']),          // step 0, checkpoint 0
                toolCallResponse('search', ['q' => 'second']),         // step 1, checkpoint 1
                toolCallResponse(ExecutionRetrospectiveTool::TOOL_NAME, [
                    'checkpoint_id' => 1,
                    'guidance' => 'Better path',
                ]),                                                    // step 2, checkpoint 2 → rewind to 1
                finalResponse('Answer'),                               // step 3, checkpoint 1 (reset)
            ],
            extraTools: new Tools($searchTool),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('Question'));
        $finalState = $loop->execute($state);

        // After rewind to checkpoint 1, counter resets to 1
        // Then step 3 gets CHECKPOINT 1, incrementing to 2
        $checkpointCount = $finalState->metadata()->get(
            ExecutionRetrospectiveHook::CHECKPOINT_COUNT_KEY, 0
        );
        expect($checkpointCount)->toBe(2);
    });
});
