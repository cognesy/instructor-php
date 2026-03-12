<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Drivers;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;


function _inc(int $x): int { return $x + 1; }
function _dbl(int $x): int { return $x * 2; }

it('executes multiple tool calls and preserves follow-up order and usage', function () {
    $resp = new InferenceResponse(
        content: '',
        toolCalls: new ToolCalls(
            new ToolCall('_inc', ['x' => 1]),
            new ToolCall('_dbl', ['x' => 2])
        ),
        usage: new InferenceUsage(3,4)
    );
    $driver = new FakeInferenceDriver([$resp]);

    $tools = new Tools(
        FunctionTool::fromCallable(_inc(...)),
        FunctionTool::fromCallable(_dbl(...)),
    );

    $state = (new ToolUseState())
        ->withMessages(Messages::fromString('run multiple'));

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(
            inference: InferenceRuntime::fromProvider(
                provider: LLMProvider::new()->withDriver($driver),
            ),
        )
    );

    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();

    // two executions
    expect(count($step?->toolExecutions()->all()))->toBe(2);
    // usage passthrough
    expect($step?->usage()->toArray())->toMatchArray(['input' => 3, 'output' => 4]);
    expect($step?->inferenceResponse())->not()->toBeNull();

    // Verify tool call invocations and results via typed accessors
    $allMessages = $state->messages()->all();
    $invocations = array_values(array_filter($allMessages, fn(Message $m) => $m->hasToolCalls()));
    $results = array_values(array_filter($allMessages, fn(Message $m) => $m->toolResult() !== null));

    expect(count($invocations))->toBe(2)
        ->and($invocations[0]->toolCalls()->first()->name())->toBe('_inc')
        ->and($invocations[1]->toolCalls()->first()->name())->toBe('_dbl');

    expect(count($results))->toBe(2)
        ->and($results[0]->toolResult()->toolName())->toBe('_inc')
        ->and($results[1]->toolResult()->toolName())->toBe('_dbl');
});
