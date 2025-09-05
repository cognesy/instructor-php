<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _inc_lb(int $x): int { return $x + 1; }

it('hasNextStep is true when no current step', function () {
    $tu = new ToolUse();
    expect($tu->hasNextStep())->toBeTrue();
});

it('finalStep respects StepsLimit(1)', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ])),
    ]);
    $toolUse = (new ToolUse(continuationCriteria: [ new StepsLimit(1) ]))
        ->withDriver(new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver)))
        ->withTools([ \Cognesy\Addons\ToolUse\Tools\FunctionTool::fromCallable(_inc_lb(...)) ]);

    $toolUse->finalStep();
    expect($toolUse->state()->stepCount())->toBe(1);
});

it('accumulates usage across steps', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ]), usage: new Usage(2,3)),
        new InferenceResponse(content: 'ok', usage: new Usage(4,5)),
    ]);

    $toolUse = (new ToolUse)
        ->withDriver(new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver)))
        ->withTools([ \Cognesy\Addons\ToolUse\Tools\FunctionTool::fromCallable(_inc_lb(...)) ]);

    $toolUse->nextStep();
    $toolUse->finalStep();

    expect($toolUse->state()->usage()->toArray())->toMatchArray(['input' => 6, 'output' => 8]);
});

