<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Agents\Tests\Support\TestAgentLoop;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

function makeTestLoop(LLMProvider $llm, Tools $tools, int $maxIterations): TestAgentLoop
{
    $events = new EventDispatcher();
    $interceptor = new PassThroughInterceptor();
    $driver = new ToolCallingDriver(
        inference: InferenceRuntime::fromProvider(
            provider: $llm,
            events: $events,
        ),
        llm: $llm,
        events: $events,
    );
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
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Hello! How can I help you?')),
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
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('')->withToolCalls(new ToolCalls($toolCall))),
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Tool executed successfully.')),
        ]);

        $testTool = FakeTool::returning('test_tool', 'A test tool', 'Executed');

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
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('')->withToolCalls(new ToolCalls($toolCall))),
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Tool executed successfully.')),
        ]);

        $testTool = FakeTool::returning('test_tool', 'A test tool', 'Executed');

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

    it('preserves provider text exactly when tool calls are present', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('{"arg":"val"}')->withToolCalls(new ToolCalls($toolCall))),
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Tool executed successfully.')),
        ]);

        $testTool = FakeTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->steps()->stepAt(0);
        $messages = $step->outputMessages()->all();
        $contents = array_map(
            static fn(Message $message): string => $message->content()->toString(),
            $messages,
        );

        expect(count($messages))->toBe(2);
        expect($contents)->toContain('{"arg":"val"}');
    });

    it('appends natural language content when tool calls are present', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Calling tool')->withToolCalls(new ToolCalls($toolCall))),
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Tool executed successfully.')),
        ]);

        $testTool = FakeTool::returning('test_tool', 'A test tool', 'Executed');

        $llm = LLMProvider::new()->withDriver($driver);
        $tools = new Tools($testTool);
        $agent = makeTestLoop($llm, $tools, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->steps()->stepAt(0);
        $messages = $step->outputMessages()->all();

        expect(count($messages))->toBe(2);
        expect($messages[0]->content()->toString())->toBe('Calling tool');
    });

    it('hydrates state llm config from driver when missing', function () {
        $driver = new FakeInferenceDriver([
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Hydrated')),
        ]);

        $defaultConfig = new LLMConfig(
            model: 'driver-default-model',
            maxTokens: 2048,
        );

        $llm = LLMProvider::new()
            ->withLLMConfig($defaultConfig)
            ->withDriver($driver);
        $agent = makeTestLoop($llm, new Tools(), 1);

        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $finalState = $agent->execute($state);

        expect($finalState->llmConfig())->not->toBeNull()
            ->and($finalState->llmConfig()?->model)->toBe('driver-default-model')
            ->and($finalState->llmConfig()?->maxTokens)->toBe(2048);
    });

    it('prefers state llm config over driver defaults', function () {
        $driver = new FakeInferenceDriver([
            new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('Override')),
        ]);

        $driverConfig = new LLMConfig(model: 'driver-model');
        $stateConfig = new LLMConfig(model: 'state-model');

        $llm = LLMProvider::new()
            ->withLLMConfig($driverConfig)
            ->withDriver($driver);
        $agent = makeTestLoop($llm, new Tools(), 1);

        $seenModel = null;
        $agent->onEvent(InferenceRequestStarted::class, function (InferenceRequestStarted $event) use (&$seenModel): void {
            $seenModel = $event->model;
        });

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('Hi'))
            ->withLLMConfig($stateConfig);

        $finalState = $agent->execute($state);

        expect($seenModel)->toBe('state-model')
            ->and($finalState->llmConfig()?->model)->toBe('state-model');
    });
});
