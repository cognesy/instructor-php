<?php declare(strict_types=1);

use Cognesy\Agents\AgentBuilder\Capabilities\File\EditFileTool;
use Cognesy\Agents\AgentBuilder\Capabilities\File\ReadFileTool;
use Cognesy\Agents\AgentBuilder\Capabilities\File\WriteFileTool;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Agents\Tests\Support\TestAgentLoop;
use Cognesy\Agents\Tests\Support\TestHelpers;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('Agent with File Tools', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_file_test_' . uniqid();
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
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('reads file using read_file tool', function () {
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

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Read the test file')
        );

        $finalState = $agent->execute($state);

        expect($finalState->stepCount())->toBe(2);
        $firstStep = $finalState->steps()->stepAt(0);
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

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create a new file')
        );

        $agent->execute($state);

        expect(file_exists($testFile))->toBeTrue();
        expect(file_get_contents($testFile))->toBe($content);
    });

    it('edits file using edit_file tool', function () {
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

        $agent = ($this->makeAgent)($tools, $driver, 2);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Change World to Universe')
        );

        $agent->execute($state);

        expect(file_get_contents($testFile))->toBe('Hello Universe');
    });

    it('chains multiple file operations', function () {
        $testFile = $this->tempDir . '/chained.txt';

        $writeCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'write_file',
            'arguments' => json_encode(['path' => $testFile, 'content' => 'Original content']),
        ]);

        $readCall = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'read_file',
            'arguments' => json_encode(['path' => $testFile]),
        ]);

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

        $agent = ($this->makeAgent)($tools, $driver, 4);

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Create, read, and modify a file')
        );

        $finalState = $agent->execute($state);

        expect($finalState->stepCount())->toBeGreaterThanOrEqual(2);
        expect(file_get_contents($testFile))->toBe('Modified content');
    });
});
