<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Agents\Core\Tools\MockTool;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;

describe('Agent Loop', function () {
    it('completes a simple interaction', function () {
        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: 'Hello! How can I help you?'),
        ]);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Hi')
        );

        $finalState = $agent->execute($state);

        expect($finalState->stepCount())->toBe(1);
        expect($finalState->currentStep()->stepType())->toBe(AgentStepType::FinalResponse);
        expect(trim($finalState->currentStep()->outputMessages()->toString()))->toBe('Hello! How can I help you?');
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
        $agent = AgentBuilder::base()
            ->withTools(new Tools($testTool))
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);

        expect($finalState->stepCount())->toBe(2);
        expect($finalState->stepAt(0)->stepType())->toBe(AgentStepType::ToolExecution);
        expect($finalState->stepAt(1)->stepType())->toBe(AgentStepType::FinalResponse);
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
        $agent = AgentBuilder::base()
            ->withTools(new Tools($testTool))
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->stepAt(0);
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
        $agent = AgentBuilder::base()
            ->withTools(new Tools($testTool))
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->execute($state);
        $step = $finalState->stepAt(0);
        $messages = $step->outputMessages()->toArray();

        expect(count($messages))->toBe(3);
        expect($messages[2]['content'] ?? null)->toBe('Calling tool');
    });
});
