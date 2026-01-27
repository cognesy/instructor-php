<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendFinalResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

describe('AppendFinalResponse', function () {
    it('appends final assistant response when no tool calls are present', function () {
        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $outputMessages = Messages::fromString('Hello!', 'assistant');
        $step = new AgentStep(outputMessages: $outputMessages);
        $state = $state->withCurrentStep($step);

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        expect($result->messages()->count())->toBe(2);
        expect($result->messages()->last()->toString())->toBe('Hello!');
    });

    it('does not append when the step has tool calls', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);
        $response = new InferenceResponse(toolCalls: new ToolCalls($toolCall));
        $outputMessages = Messages::fromString('Hidden response', 'assistant');
        $step = new AgentStep(
            outputMessages: $outputMessages,
            inferenceResponse: $response,
        );
        $state = AgentState::empty()
            ->withMessages(Messages::fromString('Hi'))
            ->withCurrentStep($step);

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        expect($result->messages()->count())->toBe(1);
        expect($result->messages()->last()->toString())->toBe('Hi');
    });

    it('handles empty outputMessages gracefully', function () {
        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $step = new AgentStep(outputMessages: Messages::empty());
        $state = $state->withCurrentStep($step);

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        expect($result->messages()->count())->toBe(1);
        expect($result->messages()->last()->toString())->toBe('Hi');
    });

    it('returns state unchanged when currentStep is null', function () {
        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        expect($result->messages()->count())->toBe(1);
    });

    it('does not append response text from step with tool calls even if response exists', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);
        $response = new InferenceResponse(toolCalls: new ToolCalls($toolCall));
        // Step has both tool calls AND a response message
        $outputMessages = Messages::fromArray([
            ['role' => 'assistant', 'content' => 'I will call the tool'],
            ['role' => 'tool', 'content' => 'Tool result'],
            ['role' => 'assistant', 'content' => 'Here is the result after tool'],
        ]);
        $step = new AgentStep(
            outputMessages: $outputMessages,
            inferenceResponse: $response,
        );
        $state = AgentState::empty()
            ->withMessages(Messages::fromString('Hi'))
            ->withCurrentStep($step);

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        // Should NOT append any response since step has tool calls
        expect($result->messages()->count())->toBe(1);
        expect($result->messages()->last()->toString())->toBe('Hi');
    });

    it('skips assistant messages with empty content', function () {
        $state = AgentState::empty()->withMessages(Messages::fromString('Hi'));
        $outputMessages = Messages::fromArray([
            ['role' => 'assistant', 'content' => ''],
            ['role' => 'assistant', 'content' => 'Actual response'],
        ]);
        $step = new AgentStep(outputMessages: $outputMessages);
        $state = $state->withCurrentStep($step);

        $processor = new AppendFinalResponse();
        $result = $processor->process($state);

        expect($result->messages()->count())->toBe(2);
        expect($result->messages()->last()->toString())->toBe('Actual response');
    });
});
