<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Core\Collections\Tools;
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

describe('Agent metadata-based trace isolation', function () {
    it('tags tool traces with metadata and includes them in current execution context', function () {
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

        // After step 1 (tool call): trace messages have is_trace metadata
        $firstState = $states[0];
        $allMessages = $firstState->store()->toMessages();
        $traces = $allMessages->filter(fn(Message $m) => $m->metadata()->get('is_trace') === true);
        expect($traces->count())->toBeGreaterThan(0);

        // Compiler includes traces from current execution
        $compiler = new ConversationWithCurrentToolTrace();
        $compiled = $compiler->compile($firstState);
        expect($compiled->filter(fn(Message $m): bool => $m->isTool())->count())->toBeGreaterThan(0);

        // After step 2 (final response): tool traces fed into inference via compiler
        $secondState = $states[1];
        $inputMessages = $secondState->currentStepOrLast()?->inputMessages() ?? Messages::empty();
        expect($inputMessages->filter(fn(Message $m): bool => $m->isTool())->count())->toBeGreaterThan(0);

        // Final state: response in messages
        $finalMessages = $secondState->messages();
        expect($finalMessages->last()->toString())->toBe('All done.');
    });

    it('produces messages with LLM-compatible tool call structure', function () {
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

        // After tool step: messages should contain an assistant+tool pair with metadata
        $allMessages = $states[0]->store()->toMessages();
        $traceMessages = $allMessages->filter(fn(Message $m) => $m->metadata()->get('is_trace') === true);
        $all = $traceMessages->all();

        // Exactly 2 trace messages per tool call: invocation + result
        expect($all)->toHaveCount(2);

        // First: assistant message carrying the tool_calls metadata (LLM invocation record)
        $invocation = $all[0];
        expect($invocation->isAssistant())->toBeTrue();
        $toolCallsMetadata = $invocation->metadata()->get('tool_calls');
        expect($toolCallsMetadata)->toBeArray()
            ->and($toolCallsMetadata[0]['id'])->toBe('call_abc')
            ->and($toolCallsMetadata[0]['function']['name'])->toBe('lookup');
        // Has tool_execution_id from formatter
        expect($invocation->metadata()->get('tool_execution_id'))->not->toBeNull();

        // Second: tool message with tool_call_id linking back to the invocation
        $result = $all[1];
        expect($result->isTool())->toBeTrue()
            ->and($result->toString())->toBe('sunny and warm')
            ->and($result->metadata()->get('tool_call_id'))->toBe('call_abc')
            ->and($result->metadata()->get('tool_name'))->toBe('lookup')
            ->and($result->metadata()->get('tool_execution_id'))->not->toBeNull();
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

        $compiler = new ConversationWithCurrentToolTrace();

        // After step 1: 1 tool call pair (2 trace messages)
        $tracesAfterStep1 = $states[0]->store()->toMessages()
            ->filter(fn(Message $m) => $m->metadata()->get('is_trace') === true);
        expect($tracesAfterStep1->count())->toBe(2)
            ->and($tracesAfterStep1->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(1);

        // After step 2: accumulated to 2 tool call pairs (4 trace messages)
        $tracesAfterStep2 = $states[1]->store()->toMessages()
            ->filter(fn(Message $m) => $m->metadata()->get('is_trace') === true);
        expect($tracesAfterStep2->count())->toBe(4)
            ->and($tracesAfterStep2->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(2);

        // Step 2 input should include tool traces from step 1 (via compiler)
        $step2Input = $states[1]->currentStepOrLast()?->inputMessages() ?? Messages::empty();
        expect($step2Input->filter(fn(Message $m): bool => $m->isTool())->count())->toBeGreaterThanOrEqual(1);

        // Final state: response in messages
        $final = $states[2];
        expect($final->messages()->last()->toString())->toBe('Final answer.');

        // Old execution traces are filtered out after next execution
        $continued = $final->forNextExecution();
        $compiled = $compiler->compile($continued);
        expect($compiled->filter(fn(Message $m) => $m->metadata()->get('is_trace') === true)->count())->toBe(0);
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

        // During tool execution: original user message still in messages
        $mainDuringTool = $states[0]->messages();
        // messages() returns DEFAULT section — now includes traces too
        $userMessages = $mainDuringTool->filter(fn(Message $m): bool => $m->isUser());
        expect($userMessages->count())->toBe(1)
            ->and($userMessages->first()->toString())->toBe('Hello agent');

        // After completion: user message still there, final response appended
        $mainAfter = $states[1]->messages();
        $userAfter = $mainAfter->filter(fn(Message $m): bool => $m->isUser());
        expect($userAfter->count())->toBe(1)
            ->and($userAfter->first()->toString())->toBe('Hello agent');
        expect($mainAfter->last()->toString())->toBe('Done.');
    });
});
