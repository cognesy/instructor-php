<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Core;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\Agent\Tools\Testing\MockTool;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

describe('Agent Loop', function () {
    it('completes a simple interaction', function () {
        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: 'Hello! How can I help you?'),
        ]);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Hi')
        );

        $finalState = $agent->finalStep($state);

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
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Use the tool')
        );

        $finalState = $agent->finalStep($state);

        expect($finalState->stepCount())->toBe(2);
        expect($finalState->stepAt(0)->stepType())->toBe(AgentStepType::ToolExecution);
        expect($finalState->stepAt(1)->stepType())->toBe(AgentStepType::FinalResponse);
    });
});
