<?php declare(strict_types=1);

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\BashTool;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

describe('Agent with BashTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_bash_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    it('executes bash command and returns output', function () {
        // Arrange: Create mock responses
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'bash',
            'arguments' => json_encode(['command' => 'echo "Hello from bash"']),
        ]);

        $driver = new FakeInferenceDriver([
            // First response: LLM calls bash tool
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            // Second response: LLM provides final answer
            new InferenceResponse(
                content: 'The command output was: Hello from bash',
            ),
        ]);

        $bashTool = new BashTool(baseDir: $this->tempDir);
        $tools = new Tools($bashTool);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Run echo "Hello from bash"')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert
        expect($finalState->stepCount())->toBeGreaterThanOrEqual(1);
        expect($driver->responseCalls)->toBe(2);
    });

    it('handles tool execution in step-by-step mode', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'bash',
            'arguments' => json_encode(['command' => 'pwd']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'Current directory is shown.',
            ),
        ]);

        $bashTool = new BashTool(baseDir: $this->tempDir);
        $tools = new Tools($bashTool);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('What is the current directory?')
        );

        // Act: Manual stepping
        $stepCount = 0;
        while ($agent->hasNextStep($state)) {
            $state = $agent->nextStep($state);
            $stepCount++;

            // First step should have tool call
            if ($stepCount === 1) {
                expect($state->currentStep()->hasToolCalls())->toBeTrue();
                expect($state->currentStep()->stepType())->toBe(AgentStepType::ToolExecution);
            }
        }

        // Assert
        expect($stepCount)->toBe(2);
    });

    it('uses iterator pattern for execution', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_3',
            'name' => 'bash',
            'arguments' => json_encode(['command' => 'ls']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'Listed files.',
            ),
        ]);

        $bashTool = new BashTool(baseDir: $this->tempDir);
        $tools = new Tools($bashTool);

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('List files')
        );

        // Act: Use iterator
        $steps = [];
        foreach ($agent->iterator($state) as $currentState) {
            $steps[] = $currentState->currentStep();
        }

        // Assert
        expect($steps)->toHaveCount(2);
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution);
        expect($steps[1]->stepType())->toBe(AgentStepType::FinalResponse);
    });
});
