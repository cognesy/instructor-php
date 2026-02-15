<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

describe('AgentState metadata-based trace filtering', function () {
    it('includes current execution traces in compiled messages', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => '{}',
        ]);
        $step = new AgentStep(
            outputMessages: Messages::fromString('trace', 'tool'),
            inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
        );

        $state = AgentState::empty()->withUserMessage('hello');
        $state = $state->withCurrentStep($step);

        $compiler = new ConversationWithCurrentToolTrace();
        $messages = $compiler->compile($state);

        expect($messages->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(1);
    });

    it('excludes old execution traces after forNextExecution', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => '{}',
        ]);
        $traceStep = new AgentStep(
            outputMessages: Messages::fromString('trace', 'tool'),
            inferenceResponse: new InferenceResponse(toolCalls: new ToolCalls($toolCall)),
        );
        $finalStep = new AgentStep(
            outputMessages: Messages::fromString('done', 'assistant'),
            inferenceResponse: new InferenceResponse(),
        );

        $state = AgentState::empty()->withUserMessage('hello');
        $state = $state->withCurrentStep($traceStep);
        $state = $state->withCurrentStepCompleted();
        $state = $state->withCurrentStep($finalStep);
        $state = $state->withCurrentStepCompleted();
        $state = $state->withExecutionCompleted();

        // After next execution, old traces should be filtered out
        $continued = $state->forNextExecution();

        $compiler = new ConversationWithCurrentToolTrace();
        $messages = $compiler->compile($continued);

        expect($messages->filter(fn(Message $m): bool => $m->isTool())->count())->toBe(0);
    });
});
