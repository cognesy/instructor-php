<?php declare(strict_types=1);

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Enums\AgentStepType;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Bash\BashTool;
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
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Run echo "Hello from bash"')
        );

        // Act
        $finalState = $agent->execute($state);

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
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('What is the current directory?')
        );

        // Act: Manual stepping using iterate()
        $stepCount = 0;
        foreach ($agent->iterate($state) as $currentState) {
            $stepCount++;

            // First step should have tool call
            if ($stepCount === 1) {
                expect($currentState->currentStep()->hasToolCalls())->toBeTrue();
                expect($currentState->currentStep()->stepType())->toBe(AgentStepType::ToolExecution);
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
            ->withDriver(new \Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('List files')
        );

        // Act: Use iterate()
        $steps = [];
        foreach ($agent->iterate($state) as $currentState) {
            $steps[] = $currentState->currentStep();
        }

        // Assert
        expect($steps)->toHaveCount(2);
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution);
        expect($steps[1]->stepType())->toBe(AgentStepType::FinalResponse);
    });
});
