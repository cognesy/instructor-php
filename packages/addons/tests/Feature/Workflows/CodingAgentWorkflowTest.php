<?php declare(strict_types=1);

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\BashTool;
use Cognesy\Addons\Agent\Capabilities\File\EditFileTool;
use Cognesy\Addons\Agent\Capabilities\File\ReadFileTool;
use Cognesy\Addons\Agent\Capabilities\File\WriteFileTool;
use Cognesy\Addons\Agent\Capabilities\Tasks\PersistTasksProcessor;
use Cognesy\Addons\Agent\Capabilities\Tasks\TodoWriteTool;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;
use Tests\Addons\Support\TestHelpers;

describe('Coding Agent Workflow', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/coding_agent_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('completes a multi-step coding task with write and edit', function () {
        // Scenario: Agent writes a file, then edits it
        $configPath = $this->tempDir . '/config.php';

        // Step 1: Agent writes the config file
        $writeCall = ToolCall::fromArray([
            'id' => 'call_write',
            'name' => 'write_file',
            'arguments' => json_encode([
                'path' => $configPath,
                'content' => "<?php\nreturn [\n    'debug' => false,\n];",
            ]),
        ]);

        // Step 2: Agent edits the file
        $editCall = ToolCall::fromArray([
            'id' => 'call_edit',
            'name' => 'edit_file',
            'arguments' => json_encode([
                'path' => $configPath,
                'old_string' => "'debug' => false",
                'new_string' => "'debug' => true",
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($writeCall)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($editCall)),
            new InferenceResponse(content: 'Config file created and updated.'),
        ]);

        $tools = new Tools(
            WriteFileTool::inDirectory($this->tempDir),
            EditFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create a config file and set debug to true')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert workflow completed
        expect($finalState->stepCount())->toBeGreaterThanOrEqual(2);

        // Assert file was created and edited correctly
        expect(file_exists($configPath))->toBeTrue();
        $content = file_get_contents($configPath);
        expect($content)->toContain("'debug' => true");
    });

    it('executes bash and file operations in sequence', function () {
        // Scenario: Agent uses bash to check environment, then creates file

        // Step 1: Check PHP version
        $bashCall = ToolCall::fromArray([
            'id' => 'call_bash',
            'name' => 'bash',
            'arguments' => json_encode(['command' => 'php --version']),
        ]);

        // Step 2: Write a file with the version info
        $versionFile = $this->tempDir . '/version.txt';
        $writeCall = ToolCall::fromArray([
            'id' => 'call_write',
            'name' => 'write_file',
            'arguments' => json_encode([
                'path' => $versionFile,
                'content' => 'PHP version checked successfully',
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($bashCall)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($writeCall)),
            new InferenceResponse(content: 'Environment verified and documented.'),
        ]);

        $tools = new Tools(
            new BashTool(baseDir: $this->tempDir),
            WriteFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Check PHP version and document it')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert
        expect($finalState->stepCount())->toBe(3);
        expect(file_exists($versionFile))->toBeTrue();
    });

    it('persists tasks across steps using processor', function () {
        $todoCall = ToolCall::fromArray([
            'id' => 'call_todo',
            'name' => 'todo_write',
            'arguments' => json_encode([
                'todos' => [
                    ['content' => 'Task 1', 'status' => 'pending', 'activeForm' => 'Doing 1'],
                    ['content' => 'Task 2', 'status' => 'in_progress', 'activeForm' => 'Doing 2'],
                ],
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($todoCall)),
            new InferenceResponse(content: 'Tasks created.'),
        ]);

        $tools = new Tools(new TodoWriteTool());

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->addProcessor(new PersistTasksProcessor())
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create some tasks')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert tasks are persisted in metadata
        $tasks = $finalState->metadata()->get(TodoWriteTool::metadataKey());
        expect($tasks)->toBeArray();
        expect($tasks)->toHaveCount(2);
        expect($tasks[0]['content'])->toBe('Task 1');
        expect($tasks[1]['status'])->toBe('in_progress');
    });

    it('tracks step types correctly through workflow', function () {
        $writeCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'write_file',
            'arguments' => json_encode([
                'path' => $this->tempDir . '/test.txt',
                'content' => 'Hello',
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($writeCall)),
            new InferenceResponse(content: 'File written.'),
        ]);

        $tools = new Tools(WriteFileTool::inDirectory($this->tempDir));

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Write a file')
        );

        // Act - collect step types as we iterate
        $stepTypes = [];
        foreach ($agent->iterator($state) as $currentState) {
            $stepTypes[] = $currentState->currentStep()->stepType();
        }

        // Assert step sequence
        expect($stepTypes)->toHaveCount(2);
        expect($stepTypes[0])->toBe(AgentStepType::ToolExecution);
        expect($stepTypes[1])->toBe(AgentStepType::FinalResponse);
    });

    it('handles multiple file operations correctly', function () {
        $file1 = $this->tempDir . '/file1.txt';
        $file2 = $this->tempDir . '/file2.txt';

        // Write first file
        $write1 = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'write_file',
            'arguments' => json_encode(['path' => $file1, 'content' => 'Content 1']),
        ]);

        // Write second file
        $write2 = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'write_file',
            'arguments' => json_encode(['path' => $file2, 'content' => 'Content 2']),
        ]);

        // Read both files
        $read1 = ToolCall::fromArray([
            'id' => 'call_3',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $file1]),
        ]);

        $read2 = ToolCall::fromArray([
            'id' => 'call_4',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $file2]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($write1)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($write2)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($read1, $read2)),
            new InferenceResponse(content: 'Both files created and verified.'),
        ]);

        $tools = new Tools(
            WriteFileTool::inDirectory($this->tempDir),
            ReadFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create two files and verify them')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert - step count depends on agent implementation
        expect($finalState->stepCount())->toBeGreaterThanOrEqual(2);
        expect(file_get_contents($file1))->toBe('Content 1');
        expect(file_get_contents($file2))->toBe('Content 2');
    });

    it('can access tool execution results from step', function () {
        $testFile = $this->tempDir . '/result.txt';
        file_put_contents($testFile, 'Test content');

        $readCall = ToolCall::fromArray([
            'id' => 'call_read',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $testFile]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($readCall)),
            new InferenceResponse(content: 'File contains test content.'),
        ]);

        $tools = new Tools(ReadFileTool::inDirectory($this->tempDir));

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Read the test file')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert - verify we can access tool execution from step
        $firstStep = $finalState->stepAt(0);
        expect($firstStep->hasToolCalls())->toBeTrue();
        expect($firstStep->toolExecutions()->hasExecutions())->toBeTrue();

        $executions = $firstStep->toolExecutions();
        expect(count($executions->all()))->toBe(1);

        $execution = $executions->all()[0];
        expect($execution->toolCall()->name())->toBe('read_file');
        expect($execution->result()->isSuccess())->toBeTrue();
    });
});
