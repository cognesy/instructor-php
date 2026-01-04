<?php declare(strict_types=1);

use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Tools\File\EditFileTool;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\WriteFileTool;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;
use Tests\Addons\Support\TestHelpers;

require_once __DIR__ . '/../../Support/FakeInferenceDriver.php';
require_once __DIR__ . '/../../Support/TestHelpers.php';

describe('Agent with File Tools', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_file_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('reads file using read_file tool', function () {
        // Setup: Create a test file
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, "Hello World");

        $toolCall = ToolCall::fromArray([
            'id' => 'call_read',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $testFile]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'The file contains "Hello World"',
            ),
        ]);

        $tools = new Tools(
            ReadFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentFactory::default(tools: $tools)
            ->with(driver: new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm));

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Read the test file')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert
        expect($finalState->stepCount())->toBe(2);
        // Verify the read was performed (by checking that tool was called)
        $firstStep = $finalState->stepAt(0);
        expect($firstStep->hasToolCalls())->toBeTrue();
        expect($firstStep->toolCalls()->first()->name())->toBe('read_file');
    });

    it('writes file using write_file tool', function () {
        $testFile = $this->tempDir . '/new_file.txt';
        $content = 'New file content';

        $toolCall = ToolCall::fromArray([
            'id' => 'call_write',
            'name' => 'write_file',
            'arguments' => json_encode(['path' => $testFile, 'content' => $content]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'File created successfully',
            ),
        ]);

        $tools = new Tools(
            WriteFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentFactory::default(tools: $tools)
            ->with(driver: new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm));

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create a new file')
        );

        // Act
        $agent->finalStep($state);

        // Assert: Verify file was actually created
        expect(file_exists($testFile))->toBeTrue();
        expect(file_get_contents($testFile))->toBe($content);
    });

    it('edits file using edit_file tool', function () {
        // Setup: Create a file to edit
        $testFile = $this->tempDir . '/edit_me.txt';
        file_put_contents($testFile, 'Hello World');

        $toolCall = ToolCall::fromArray([
            'id' => 'call_edit',
            'name' => 'edit_file',
            'arguments' => json_encode([
                'path' => $testFile,
                'old_string' => 'World',
                'new_string' => 'Universe',
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(
                content: '',
                toolCalls: new ToolCalls($toolCall),
            ),
            new InferenceResponse(
                content: 'File edited successfully',
            ),
        ]);

        $tools = new Tools(
            EditFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentFactory::default(tools: $tools)
            ->with(driver: new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm));

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Change World to Universe')
        );

        // Act
        $agent->finalStep($state);

        // Assert: Verify edit was applied
        expect(file_get_contents($testFile))->toBe('Hello Universe');
    });

    it('chains multiple file operations', function () {
        $testFile = $this->tempDir . '/chained.txt';

        // First: write file
        $writeCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'write_file',
            'arguments' => json_encode(['path' => $testFile, 'content' => 'Original content']),
        ]);

        // Second: read file
        $readCall = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $testFile]),
        ]);

        // Third: edit file
        $editCall = ToolCall::fromArray([
            'id' => 'call_3',
            'name' => 'edit_file',
            'arguments' => json_encode([
                'path' => $testFile,
                'old_string' => 'Original',
                'new_string' => 'Modified',
            ]),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($writeCall)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($readCall)),
            new InferenceResponse(content: '', toolCalls: new ToolCalls($editCall)),
            new InferenceResponse(content: 'All operations complete'),
        ]);

        $tools = new Tools(
            ReadFileTool::inDirectory($this->tempDir),
            WriteFileTool::inDirectory($this->tempDir),
            EditFileTool::inDirectory($this->tempDir),
        );

        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentFactory::default(tools: $tools)
            ->with(driver: new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm));

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create, read, and modify a file')
        );

        // Act
        $finalState = $agent->finalStep($state);

        // Assert - file operations should complete regardless of step count
        expect($finalState->stepCount())->toBeGreaterThanOrEqual(2);
        expect(file_get_contents($testFile))->toBe('Modified content');
    });
});
