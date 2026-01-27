<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Processors;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\PersistTasksProcessor;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\TodoResult;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\TodoWriteTool;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;

describe('PersistTasksProcessor', function () {

    it('can process AgentState', function () {
        $processor = new PersistTasksProcessor();
        $state = AgentState::empty();

        expect($processor->canProcess($state))->toBeTrue();
    });

    it('returns state unchanged when no current step', function () {
        $processor = new PersistTasksProcessor();
        $state = AgentState::empty();

        $result = $processor->process($state);

        expect($result)->toBe($state);
    });

    it('returns state unchanged when step has no tool executions', function () {
        $processor = new PersistTasksProcessor();
        $step = new AgentStep();
        $state = AgentState::empty()->withCurrentStep($step);

        $result = $processor->process($state);

        expect($result->metadata()->get(TodoWriteTool::metadataKey()))->toBeNull();
    });

    it('persists tasks from todo_write tool execution', function () {
        $processor = new PersistTasksProcessor();

        // Create a tool call for todo_write
        $toolCall = ToolCall::fromArray([
            'id' => 'call_123',
            'name' => 'todo_write',
            'arguments' => '{}',
        ]);

        // Create the result that todo_write returns
        $todoResult = new TodoResult(
            success: true,
            tasks: [
                ['content' => 'Task 1', 'status' => 'pending', 'activeForm' => 'Doing 1'],
                ['content' => 'Task 2', 'status' => 'in_progress', 'activeForm' => 'Doing 2'],
            ],
            summary: 'Tasks: 0/2 completed',
            rendered: "1. ○ Task 1\n2. ◐ Doing 2",
        );

        // Create tool execution
        $now = new \DateTimeImmutable();
        $execution = new ToolExecution(
            toolCall: $toolCall,
            result: Result::success($todoResult),
            startedAt: $now,
            completedAt: $now,
        );

        $toolExecutions = new ToolExecutions($execution);

        // Create step with tool executions
        $response = new InferenceResponse(toolCalls: new ToolCalls($toolCall));
        $step = new AgentStep(
            toolExecutions: $toolExecutions,
            inferenceResponse: $response,
        );

        $state = AgentState::empty()->withCurrentStep($step);

        // Process
        $result = $processor->process($state);

        // Verify tasks are persisted in metadata
        $persistedTasks = $result->metadata()->get(TodoWriteTool::metadataKey());
        expect($persistedTasks)->toBeArray();
        expect($persistedTasks)->toHaveCount(2);
        expect($persistedTasks[0]['content'])->toBe('Task 1');
        expect($persistedTasks[1]['status'])->toBe('in_progress');
    });

    it('ignores failed tool executions', function () {
        $processor = new PersistTasksProcessor();

        $toolCall = ToolCall::fromArray([
            'id' => 'call_456',
            'name' => 'todo_write',
            'arguments' => '{}',
        ]);

        // Create failed execution
        $now = new \DateTimeImmutable();
        $execution = new ToolExecution(
            toolCall: $toolCall,
            result: Result::failure(new \Exception('Tool failed')),
            startedAt: $now,
            completedAt: $now,
        );

        $toolExecutions = new ToolExecutions($execution);
        $response = new InferenceResponse(toolCalls: new ToolCalls($toolCall));
        $step = new AgentStep(
            toolExecutions: $toolExecutions,
            inferenceResponse: $response,
        );

        $state = AgentState::empty()->withCurrentStep($step);

        $result = $processor->process($state);

        // Should not have tasks in metadata
        expect($result->metadata()->get(TodoWriteTool::metadataKey()))->toBeNull();
    });

    it('ignores non-todo_write tool executions', function () {
        $processor = new PersistTasksProcessor();

        $toolCall = ToolCall::fromArray([
            'id' => 'call_789',
            'name' => 'bash',
            'arguments' => '{"command": "ls"}',
        ]);

        $now = new \DateTimeImmutable();
        $execution = new ToolExecution(
            toolCall: $toolCall,
            result: Result::success('file1.txt'),
            startedAt: $now,
            completedAt: $now,
        );

        $toolExecutions = new ToolExecutions($execution);
        $response = new InferenceResponse(toolCalls: new ToolCalls($toolCall));
        $step = new AgentStep(
            toolExecutions: $toolExecutions,
            inferenceResponse: $response,
        );

        $state = AgentState::empty()->withCurrentStep($step);

        $result = $processor->process($state);

        expect($result->metadata()->get(TodoWriteTool::metadataKey()))->toBeNull();
    });

    it('calls next processor in chain', function () {
        $processor = new PersistTasksProcessor();
        $state = AgentState::empty();
        $nextCalled = false;

        $next = function ($state) use (&$nextCalled) {
            $nextCalled = true;
            return $state;
        };

        $processor->process($state, $next);

        expect($nextCalled)->toBeTrue();
    });
});
