<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

describe('AgentContext::withStepOutputRouted', function () {
    it('routes tool step output to execution buffer', function () {
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
                ->section(AgentContext::DEFAULT_SECTION)
                ->setMessages(Messages::fromString('user input', 'user')),
        );
        $result = $context->withStepOutputRouted($step);

        // Output went to execution buffer
        $buffer = $result->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->messages();
        expect($buffer->count())->toBe(1)
            ->and($buffer->first()->toString())->toBe('tool result');

        // Main messages unchanged
        expect($result->messages()->count())->toBe(1)
            ->and($result->messages()->first()->toString())->toBe('user input');
    });

    it('routes final response to main messages and clears execution buffer', function () {
        $step = new AgentStep(
            outputMessages: Messages::fromString('final answer', 'assistant'),
            inferenceResponse: new InferenceResponse(), // no tool calls = FinalResponse
        );

        // Pre-populate execution buffer with prior tool traces
        $store = (new AgentContext())->store()
            ->section(AgentContext::DEFAULT_SECTION)
            ->setMessages(Messages::fromString('user input', 'user'));
        $store = $store->section(AgentContext::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('prior trace', 'tool'));

        $context = new AgentContext(store: $store);
        $result = $context->withStepOutputRouted($step);

        // Final response appended to main messages
        $main = $result->messages();
        expect($main->count())->toBe(2)
            ->and($main->first()->toString())->toBe('user input')
            ->and($main->last()->toString())->toBe('final answer');

        // Execution buffer cleared
        expect($result->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });

    it('returns unchanged context for null step', function () {
        $context = new AgentContext();
        $result = $context->withStepOutputRouted(null);

        expect($result)->toBe($context);
    });

    it('returns unchanged context for step with empty output', function () {
        $step = new AgentStep(
            outputMessages: Messages::empty(),
            inferenceResponse: new InferenceResponse(),
        );

        $context = new AgentContext();
        $result = $context->withStepOutputRouted($step);

        expect($result)->toBe($context);
    });
});
