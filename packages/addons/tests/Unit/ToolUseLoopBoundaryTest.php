<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\LLMProvider;
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
    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState($tools);
        
    $toolUse = new ToolUse(
        state: $state,
        continuationCriteria: new ContinuationCriteria(new StepsLimit(1)),
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $toolUse->finalStep();
    expect($toolUse->state()->stepCount())->toBe(1);
});

it('accumulates usage across steps', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ]), usage: new Usage(2,3)),
        new InferenceResponse(content: 'ok', usage: new Usage(4,5)),
    ]);

    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState($tools);
        
    $toolUse = new ToolUse(
        state: $state,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $toolUse->nextStep();
    $toolUse->finalStep();

    expect($toolUse->state()->usage()->toArray())->toMatchArray(['input' => 6, 'output' => 8]);
});

