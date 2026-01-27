<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Processors;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendToolTraceToBuffer;
use Cognesy\Agents\Drivers\ToolCalling\ToolExecutionFormatter;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;

describe('AppendToolTraceToBuffer', function () {
    it('appends tool trace messages to execution buffer', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);
        $now = new \DateTimeImmutable();
        $execution = new ToolExecution(
            toolCall: $toolCall,
            result: Result::success('OK'),
            startedAt: $now,
            completedAt: $now,
        );
        $toolExecutions = new ToolExecutions($execution);
        $formatter = new ToolExecutionFormatter();
        $toolMessages = $formatter->makeExecutionMessages($toolExecutions);
        $outputMessages = $toolMessages->appendMessage(Message::asAssistant('Final response'));

        $step = new AgentStep(outputMessages: $outputMessages);
        $state = AgentState::empty()->withCurrentStep($step);

        $processor = new AppendToolTraceToBuffer();
        $result = $processor->process($state);

        $buffer = $result
            ->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->messages();

        expect($buffer->count())->toBe(2);
        expect($buffer->filter(fn(Message $message): bool => $message->isTool())->count())->toBe(1);
        expect($buffer->filter(fn(Message $message): bool => $message->isAssistant())->count())->toBe(1);
        expect($buffer->toString())->not->toContain('Final response');
    });

    it('handles empty outputMessages gracefully', function () {
        $step = new AgentStep(outputMessages: Messages::empty());
        $state = AgentState::empty()->withCurrentStep($step);

        $processor = new AppendToolTraceToBuffer();
        $result = $processor->process($state);

        $buffer = $result
            ->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->messages();

        expect($buffer->isEmpty())->toBeTrue();
    });

    it('returns state unchanged when currentStep is null', function () {
        $state = AgentState::empty();

        $processor = new AppendToolTraceToBuffer();
        $result = $processor->process($state);

        expect($result->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });

    it('only extracts tool traces, not plain assistant messages', function () {
        $outputMessages = Messages::fromArray([
            ['role' => 'assistant', 'content' => 'Plain response without tool calls'],
        ]);

        $step = new AgentStep(outputMessages: $outputMessages);
        $state = AgentState::empty()->withCurrentStep($step);

        $processor = new AppendToolTraceToBuffer();
        $result = $processor->process($state);

        $buffer = $result
            ->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->messages();

        expect($buffer->isEmpty())->toBeTrue();
    });

    it('accumulates tool traces across multiple steps', function () {
        $processor = new AppendToolTraceToBuffer();
        $formatter = new ToolExecutionFormatter();
        $now = new \DateTimeImmutable();

        // First step with tool call
        $toolCall1 = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'tool_a',
            'arguments' => json_encode(['arg' => 'val1']),
        ]);
        $execution1 = new ToolExecution(
            toolCall: $toolCall1,
            result: Result::success('Result 1'),
            startedAt: $now,
            completedAt: $now,
        );
        $toolMessages1 = $formatter->makeExecutionMessages(new ToolExecutions($execution1));
        $step1 = new AgentStep(outputMessages: $toolMessages1);
        $state = AgentState::empty()->withCurrentStep($step1);

        $state = $processor->process($state);

        // Second step with another tool call
        $toolCall2 = ToolCall::fromArray([
            'id' => 'call_2',
            'name' => 'tool_b',
            'arguments' => json_encode(['arg' => 'val2']),
        ]);
        $execution2 = new ToolExecution(
            toolCall: $toolCall2,
            result: Result::success('Result 2'),
            startedAt: $now,
            completedAt: $now,
        );
        $toolMessages2 = $formatter->makeExecutionMessages(new ToolExecutions($execution2));
        $step2 = new AgentStep(outputMessages: $toolMessages2);
        $state = $state->withCurrentStep($step2);

        $state = $processor->process($state);

        $buffer = $state
            ->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->messages();

        // Should have accumulated traces from both steps
        // Each step produces: assistant message with tool_calls + tool result message = 2 per step
        expect($buffer->count())->toBe(4);
        expect($buffer->filter(fn(Message $message): bool => $message->isTool())->count())->toBe(2);
    });
});
