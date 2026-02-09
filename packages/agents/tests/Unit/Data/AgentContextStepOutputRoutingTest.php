<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Context\AgentContext;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

describe('AgentState::withCurrentStep metadata tagging', function () {
    it('tags tool step output messages with is_trace and execution metadata', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'my_tool',
            'arguments' => '{}',
        ]);
        $step = new AgentStep(
            outputMessages: Messages::fromString('tool result', 'tool'),
            inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
        );

        $context = new AgentContext(
            store: (new AgentContext())->store()
                ->section(ContextSections::DEFAULT)
                ->setMessages(Messages::fromString('user input', 'user')),
        );
        $state = AgentState::empty()->with(context: $context);
        $result = $state->withCurrentStep($step);

        // Output went to DEFAULT section (all messages now go to DEFAULT)
        $allMessages = $result->messages();
        expect($allMessages->count())->toBe(2)
            ->and($allMessages->first()->toString())->toBe('user input');

        // Tool result message has trace metadata
        $toolMsg = $allMessages->last();
        expect($toolMsg->toString())->toBe('tool result')
            ->and($toolMsg->metadata()->get('is_trace'))->toBeTrue()
            ->and($toolMsg->metadata()->get('step_id'))->toBe($step->id())
            ->and($toolMsg->metadata()->get('agent_id'))->not->toBeEmpty()
            ->and($toolMsg->metadata()->get('execution_id'))->not->toBeEmpty();
    });

    it('tags final response without is_trace', function () {
        $step = new AgentStep(
            outputMessages: Messages::fromString('final answer', 'assistant'),
            inferenceResponse: new InferenceResponse(), // no tool calls = FinalResponse
        );

        $context = new AgentContext(
            store: (new AgentContext())->store()
                ->section(ContextSections::DEFAULT)
                ->setMessages(Messages::fromString('user input', 'user')),
        );
        $state = AgentState::empty()->with(context: $context);
        $result = $state->withCurrentStep($step);

        // Final response appended to main messages
        $main = $result->messages();
        expect($main->count())->toBe(2)
            ->and($main->first()->toString())->toBe('user input')
            ->and($main->last()->toString())->toBe('final answer');

        // Final response has execution metadata but NOT is_trace
        $finalMsg = $main->last();
        expect($finalMsg->metadata()->get('is_trace'))->toBeNull()
            ->and($finalMsg->metadata()->get('step_id'))->toBe($step->id())
            ->and($finalMsg->metadata()->get('execution_id'))->not->toBeEmpty();
    });

    it('compiler filters out old execution traces', function () {
        // Simulate two executions: first has trace messages, second should not see them
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'my_tool',
            'arguments' => '{}',
        ]);
        $traceStep = new AgentStep(
            outputMessages: Messages::fromString('trace from exec 1', 'tool'),
            inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
        );
        $finalStep = new AgentStep(
            outputMessages: Messages::fromString('answer from exec 1', 'assistant'),
            inferenceResponse: new InferenceResponse(),
        );

        $state = AgentState::empty()
            ->withUserMessage('hello');

        // First execution: tool step + final
        $state = $state->withCurrentStep($traceStep);
        $state = $state->withCurrentStepCompleted();
        $state = $state->withCurrentStep($finalStep);
        $state = $state->withCurrentStepCompleted();
        $state = $state->withExecutionCompleted();

        // Start second execution
        $state = $state->forNextExecution();
        $state = $state->withUserMessage('second question');

        // Compiler should exclude old traces
        $compiler = new ConversationWithCurrentToolTrace();
        $compiled = $compiler->compile($state);

        // Should have: 'hello', 'answer from exec 1', 'second question' — no trace
        expect($compiled->filter(fn(Message $m) => str_contains($m->toString(), 'trace'))->count())->toBe(0)
            ->and($compiled->filter(fn(Message $m) => str_contains($m->toString(), 'hello'))->count())->toBe(1)
            ->and($compiled->filter(fn(Message $m) => str_contains($m->toString(), 'answer from exec 1'))->count())->toBe(1)
            ->and($compiled->filter(fn(Message $m) => str_contains($m->toString(), 'second question'))->count())->toBe(1);
    });

    it('returns unchanged context for step with empty output', function () {
        $step = new AgentStep(
            outputMessages: Messages::empty(),
            inferenceResponse: new InferenceResponse(),
        );

        $context = new AgentContext();
        $state = AgentState::empty()->with(context: $context);
        $result = $state->withCurrentStep($step)->context();

        expect($result)->toBe($context);
    });
});
