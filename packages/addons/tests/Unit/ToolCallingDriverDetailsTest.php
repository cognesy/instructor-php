<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
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

    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_inc(...)))
        ->withTool(FunctionTool::fromCallable(_dbl(...)));
        
    $state = (new \Cognesy\Addons\ToolUse\Data\ToolUseState($tools))
        ->withMessages(\Cognesy\Messages\Messages::fromString('run multiple'));
        
    $toolUse = new ToolUse(
        state: $state,
        driver: new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver))
    );

    $step = $toolUse->nextStep();

    // two executions
    expect(count($step->toolExecutions()->all()))->toBe(2);
    // usage passthrough
    expect($step->usage()->toArray())->toMatchArray(['input' => 3, 'output' => 4]);
    expect($step->inferenceResponse())->not()->toBeNull();

    // follow-up messages order: invocation1, result1, invocation2, result2
    $msgs = $step->messages()->toArray();
    expect(count($msgs))->toBe(4);
    // first and third assistant invocations carry tool_calls metadata
    expect(($msgs[0]['_metadata']['tool_calls'][0]['function']['name']) ?? null)->toBe('_inc');
    expect(($msgs[2]['_metadata']['tool_calls'][0]['function']['name']) ?? null)->toBe('_dbl');
    // second and fourth are tool results with tool_name metadata
    expect(($msgs[1]['_metadata']['tool_name']) ?? null)->toBe('_inc');
    expect(($msgs[3]['_metadata']['tool_name']) ?? null)->toBe('_dbl');
});

