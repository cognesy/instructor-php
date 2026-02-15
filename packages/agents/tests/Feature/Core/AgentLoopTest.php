<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Agents\Tests\Support\TestAgentLoop;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\MockTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;

function makeTestLoop(LLMProvider $llm, Tools $tools, int $maxIterations): TestAgentLoop
{
    $events = new EventDispatcher();
    $interceptor = new PassThroughInterceptor();
    $driver = new ToolCallingDriver(llm: $llm, events: $events);
    $toolExecutor = new ToolExecutor($tools, events: $events, interceptor: $interceptor);

    return new TestAgentLoop(
        tools: $tools,
        toolExecutor: $toolExecutor,
        driver: $driver,
        events: $events,
        interceptor: $interceptor,
        maxIterations: $maxIterations,
    );
}

describe('Agent Loop', function () {
    it('completes a simple interaction', function () {
        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: 'Hello! How can I help you?'),
        ]);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = makeTestLoop($llm, new Tools(), 1);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Hi')
        );

        $finalState = $agent->execute($state);

        $lastStep = $finalState->steps()->lastStep();

        expect($finalState->stepCount())->toBe(1);
        expect($lastStep?->stepType())->toBe(AgentStepType::FinalResponse);
        expect(trim((string) $lastStep?->outputMessages()->toString()))->toBe('Hello! How can I help you?');
    });

    it('completes a multi-step tool-using interaction', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Tool executed successfully.'),
        ]);

        $testTool = MockTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);

        $steps = $finalState->steps()->all();

        expect($finalState->stepCount())->toBe(2);
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution);
        expect($steps[1]->stepType())->toBe(AgentStepType::FinalResponse);
    });

    it('advances step numbers across loop iterations', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Tool executed successfully.'),
        ]);

        $testTool = MockTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $steps = $finalState->steps()->all();

        expect($steps)->toHaveCount(2);
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution);
        expect($steps[1]->stepType())->toBe(AgentStepType::FinalResponse);
    });

    it('does not append tool args content when tool calls are present', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '{"arg":"val"}', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Tool executed successfully.'),
        ]);

        $testTool = MockTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->steps()->stepAt(0);
        $messages = $step->outputMessages()->toArray();
        $contents = array_map(
            static fn(array $message): string => (string) ($message['content'] ?? ''),
            $messages,
        );

        expect(count($messages))->toBe(2);
        expect($contents)->not->toContain('{"arg":"val"}');
    });

    it('appends natural language content when tool calls are present', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: 'Calling tool', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'Tool executed successfully.'),
        ]);

        $testTool = MockTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->steps()->stepAt(0);
        $messages = $step->outputMessages()->toArray();

        expect(count($messages))->toBe(3);
        expect($messages[2]['content'] ?? null)->toBe('Calling tool');
    });
});
