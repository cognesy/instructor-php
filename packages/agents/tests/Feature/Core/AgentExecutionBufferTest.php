<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\MockTool;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('Agent execution buffer', function () {
    it('keeps tool traces ephemeral while preserving inference context', function () {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode(['arg' => 'val']),
        ]);

        $driver = new FakeInferenceDriver([
            new InferenceResponse(content: '', toolCalls: new ToolCalls($toolCall)),
            new InferenceResponse(content: 'All done.'),
        ]);

        $tool = MockTool::returning('test_tool', 'A test tool', 'Executed');
        $llm = LLMProvider::new()->withDriver($driver);
        $agent = AgentBuilder::base()
            ->withSeparatedToolTrace(true)
            ->withTools(new Tools($tool))
            ->withDriver(new ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('Use the tool'));
        $states = iterator_to_array($agent->iterate($state));

        $firstState = $states[0];
        $secondState = $states[1];

        $buffer = $firstState
            ->store()
            ->section(AgentContext::EXECUTION_BUFFER_SECTION)
            ->messages();

        expect($buffer->count())->toBe(2);
        expect($buffer->filter(fn(Message $message): bool => $message->isTool())->count())->toBe(1);

        $inputMessages = $secondState->currentStep()?->inputMessages() ?? Messages::empty();
        expect($inputMessages->filter(fn(Message $message): bool => $message->isTool())->count())->toBeGreaterThan(0);
        expect($inputMessages->filter(
            fn(Message $message): bool => $message->metadata()->hasKey('tool_calls')
        )->count())->toBeGreaterThan(0);

        $finalMessages = $secondState->messages();
        expect($finalMessages->filter(fn(Message $message): bool => $message->isTool())->count())->toBe(0);
        expect($finalMessages->last()->toString())->toBe('All done.');
        expect($secondState->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });
})->skip('hooks not integrated yet');
