<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Drivers;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

function _add(int $a, int $b): int { return $a + $b; }

it('does not produce empty assistant messages after tool call step', function () {
    // Step 1: LLM returns a tool call (content is empty as expected for tool calls)
    $toolCallResponse = new InferenceResponse(
        content: '',
        toolCalls: new ToolCalls(new ToolCall('_add', ['a' => 2, 'b' => 3])),
        usage: new Usage(10, 20),
    );

    // Step 2: LLM returns a final text answer
    $finalResponse = new InferenceResponse(
        content: 'The result is 5.',
        usage: new Usage(15, 10),
    );

    $driver = new FakeInferenceDriver([$toolCallResponse, $finalResponse]);

    $tools = new Tools(FunctionTool::fromCallable(_add(...)));

    $state = (new ToolUseState())
        ->withMessages(Messages::fromString('Add 2 and 3'));

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(
            inference: InferenceRuntime::fromProvider(
                provider: LLMProvider::new()->withDriver($driver),
            ),
        ),
    );

    // Execute step 1 (tool call)
    $state = $toolUse->nextStep($state);
    $step1 = $state->currentStep();
    expect($step1->hasToolCalls())->toBeTrue();

    // Verify no empty assistant messages without tool_calls exist in state.
    // When LLM returns a tool call, the response content is empty — this must NOT
    // be appended as a standalone assistant message, as OpenAI rejects null/empty
    // content on assistant messages that don't carry tool_calls.
    $stateMessages = $state->messages()->all();
    $emptyAssistantMessages = array_filter($stateMessages, function (Message $msg) {
        if ($msg->role()->value !== 'assistant') return false;
        if ($msg->hasToolCalls()) return false;
        // Check if content is empty via toString
        return $msg->content()->toString() === '';
    });
    expect($emptyAssistantMessages)->toBeEmpty(
        'Found empty assistant messages without tool_calls — these cause OpenAI API errors on subsequent steps'
    );

    // Execute step 2 (final answer) - this should not fail
    expect($toolUse->hasNextStep($state))->toBeTrue();
    $state = $toolUse->nextStep($state);
    $step2 = $state->currentStep();
    expect($step2->hasToolCalls())->toBeFalse();

    // The driver should have been called twice
    expect($driver->responseCalls)->toBe(2);
});
