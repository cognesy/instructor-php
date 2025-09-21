<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _inc(int $x): int { return $x + 1; }
function _dbl(int $x): int { return $x * 2; }

it('executes multiple tool calls and preserves follow-up order and usage', function () {
    $resp = new InferenceResponse(
        content: '',
        toolCalls: new ToolCalls([
            new ToolCall('_inc', ['x' => 1]),
            new ToolCall('_dbl', ['x' => 2])
        ]),
        usage: new Usage(3,4)
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
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();

    // two executions
    expect(count($step->toolExecutions()->all()))->toBe(2);
    // usage passthrough
    expect($step->usage()->toArray())->toMatchArray(['input' => 3, 'output' => 4]);
    expect($step->inferenceResponse())->not()->toBeNull();

    // follow-up messages order: initial, invocation1, result1, invocation2, result2
    $msgs = $state->messages()->toArray();
    expect(count($msgs))->toBe(5);
    // second and fourth assistant invocations carry tool_calls metadata (offset by 1 due to initial message)
    expect(($msgs[1]['_metadata']['tool_calls'][0]['function']['name']) ?? null)->toBe('_inc');
    expect(($msgs[3]['_metadata']['tool_calls'][0]['function']['name']) ?? null)->toBe('_dbl');
    // third and fifth are tool results with tool_name metadata
    expect(($msgs[2]['_metadata']['tool_name']) ?? null)->toBe('_inc');
    expect(($msgs[4]['_metadata']['tool_name']) ?? null)->toBe('_dbl');
});

