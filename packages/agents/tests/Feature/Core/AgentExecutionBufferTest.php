<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\MockTool;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('Agent execution buffer', function () {
    it('routes tool traces to execution buffer and final response to main messages', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'All done.'),
        ]);

        $tool = MockTool::returning('test_tool', 'A test tool', 'Executed');
        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools(new Tools($tool))
            ->withDriver(new ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('Use the tool'));
        $states = iterator_to_array($agent->iterate($state));

        // After step 1 (tool call): tool traces are in execution buffer, not in main messages
        $firstState = $states[0];
        $buffer = $firstState->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->messages();
        expect($buffer->count())->toBeGreaterThan(0)
            ->and($buffer->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(1);
        $mainMessages = $firstState->messages();
        expect($mainMessages->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(0);

        // After step 2 (final response): tool traces fed into inference via messagesForInference
        $secondState = $states[1];
        $inputMessages = $secondState->currentStepOrLast()?->inputMessages() ?? Messages::empty();
        expect($inputMessages->filter(fn(Message $m): bool => $m->isTool())->count())->toBeGreaterThan(0);

        // Final state: response in main messages, execution buffer cleared
        $finalMessages = $secondState->messages();
        expect($finalMessages->last()->toString())->toBe('All done.')
            ->and($finalMessages->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(0);
        expect($secondState->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });

    it('produces buffer messages with LLM-compatible tool call structure', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_abc',
            'name' => 'lookup',
            'arguments' => json_encode(['query' => 'weather']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Done.'),
        ]);

        $tool = MockTool::returning('lookup', 'Search tool', 'sunny and warm');
        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools(new Tools($tool))
            ->withDriver(new ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('What is the weather?'));
        $states = iterator_to_array($agent->iterate($state));

        // After tool step: buffer should contain an assistant+tool message pair
        $buffer = $states[0]->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->messages();
        $all = $buffer->all();

        // Exactly 2 messages per tool call: invocation + result
        expect($all)->toHaveCount(2);

        // First: assistant message carrying the tool_calls metadata (LLM invocation record)
        $invocation = $all[0];
        expect($invocation->isAssistant())->toBeTrue();
        $toolCallsMetadata = $invocation->metadata()->get('tool_calls');
        expect($toolCallsMetadata)->toBeArray()
            ->and($toolCallsMetadata[0]['id'])->toBe('call_abc')
            ->and($toolCallsMetadata[0]['function']['name'])->toBe('lookup');

        // Second: tool message with tool_call_id linking back to the invocation
        $result = $all[1];
        expect($result->isTool())->toBeTrue()
            ->and($result->toString())->toBe('sunny and warm')
            ->and($result->metadata()->get('tool_call_id'))->toBe('call_abc')
            ->and($result->metadata()->get('tool_name'))->toBe('lookup');
    });

    it('accumulates tool traces across multiple consecutive tool steps', function () {
        $call1 = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'search',
            'arguments' => json_encode(['q' => 'first']),
        ]);
        $call2 = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'search',
            'arguments' => json_encode(['q' => 'second']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($call1)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($call2)),
            new InferenceResponse(content: 'Final answer.'),
        ]);

        $tool = MockTool::returning('search', 'Search tool', 'result');
        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools(new Tools($tool))
            ->withDriver(new ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('Search twice'));
        $states = iterator_to_array($agent->iterate($state));

        // After step 1: buffer has 1 tool call pair (2 messages)
        $bufferAfterStep1 = $states[0]->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->messages();
        expect($bufferAfterStep1->count())->toBe(2)
            ->and($bufferAfterStep1->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(1);

        // After step 2: buffer accumulated to 2 tool call pairs (4 messages)
        $bufferAfterStep2 = $states[1]->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->messages();
        expect($bufferAfterStep2->count())->toBe(4)
            ->and($bufferAfterStep2->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(2);

        // Step 2 input should include tool traces from step 1
        $step2Input = $states[1]->currentStepOrLast()?->inputMessages() ?? Messages::empty();
        expect($step2Input->filter(fn(Message $m): bool => $m->isTool())->count())->toBeGreaterThanOrEqual(1);

        // Main messages should have zero tool messages throughout
        expect($states[0]->messages()->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(0);
        expect($states[1]->messages()->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(0);

        // Final state: response in main messages, buffer cleared
        $final = $states[2];
        expect($final->messages()->last()->toString())->toBe('Final answer.')
            ->and($final->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });

    it('preserves original user messages untouched during tool execution', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['x' => 1]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Done.'),
        ]);

        $tool = MockTool::returning('test_tool', 'Test', 'ok');
        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools(new Tools($tool))
            ->withDriver(new ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('Hello agent'));
        $states = iterator_to_array($agent->iterate($state));

        // During tool execution: original user message untouched in main messages
        $mainDuringTool = $states[0]->messages();
        expect($mainDuringTool->count())->toBe(1)
            ->and($mainDuringTool->first()->isUser())->toBeTrue()
            ->and($mainDuringTool->first()->toString())->toBe('Hello agent');

        // After completion: user message still there, final response appended
        $mainAfter = $states[1]->messages();
        expect($mainAfter->count())->toBe(2)
            ->and($mainAfter->first()->toString())->toBe('Hello agent')
            ->and($mainAfter->last()->toString())->toBe('Done.');
    });
});
