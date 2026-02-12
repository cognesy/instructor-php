<?php declare(strict_types=1);

use Cognesy\Agents\AgentBuilder\Capabilities\Bash\BashTool;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Agents\Tests\Support\TestAgentLoop;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('Agent with BashTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_bash_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->makeAgent = function (Tools $tools, FakeInferenceDriver $driver, int $maxIterations): TestAgentLoop {
            $events = new EventDispatcher();
            $interceptor = new PassThroughInterceptor();
            $llm = LLMProvider::new()->withDriver($driver);
            $toolDriver = new ToolCallingDriver(llm: $llm, events: $events);
            $toolExecutor = new ToolExecutor($tools, events: $events, interceptor: $interceptor);

            return new TestAgentLoop(
                tools: $tools,
                toolExecutor: $toolExecutor,
                driver: $toolDriver,
                events: $events,
                interceptor: $interceptor,
                maxIterations: $maxIterations,
            );
        };
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    it('executes bash command and returns output', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'bash',
            'arguments' => json_encode(['command' => 'echo "Hello from bash"']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'The command output was: Hello from bash',
            ),
        ]);

        $bashTool = new BashTool(baseDir: $this->tempDir);
        $tools = new Tools($bashTool);

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Run echo "Hello from bash"')
        );

        $finalState = $agent->execute($state);

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

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('What is the current directory?')
        );

        $stepCount = 0;
        foreach ($agent->iterate($state) as $currentState) {
            $stepCount++;

            if ($stepCount === 1) {
                $lastStep = $currentState->steps()->lastStep();
                expect($lastStep?->hasToolCalls())->toBeTrue();
                expect($lastStep?->stepType())->toBe(AgentStepType::ToolExecution);
            }
        }

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

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('List files')
        );

        $steps = [];
        foreach ($agent->iterate($state) as $currentState) {
            $steps[] = $currentState->steps()->lastStep();
        }

        expect($steps)->toHaveCount(2);
        expect($steps[0]->stepType())->toBe(AgentStepType::ToolExecution);
        expect($steps[1]->stepType())->toBe(AgentStepType::FinalResponse);
    });
});
