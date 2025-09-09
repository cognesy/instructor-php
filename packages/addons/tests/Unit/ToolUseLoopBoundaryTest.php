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
    $tools = new \Cognesy\Addons\ToolUse\Tools();
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $tu = new ToolUse(tools: $tools);
    expect($tu->hasNextStep($state))->toBeTrue();
});

it('finalStep respects StepsLimit(1)', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ])),
    ]);
    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
        
    $toolUse = new ToolUse(
        tools: $tools,
        continuationCriteria: new ContinuationCriteria(new StepsLimit(1)),
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $state = $toolUse->finalStep($state);
    expect($state->stepCount())->toBe(1);
});

it('accumulates usage across steps', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ]), usage: new Usage(2,3)),
        new InferenceResponse(content: 'ok', usage: new Usage(4,5)),
    ]);

    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
        
    $toolUse = new ToolUse(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $state = $toolUse->nextStep($state);
    $state = $toolUse->finalStep($state);

    expect($state->usage()->toArray())->toMatchArray(['input' => 6, 'output' => 8]);
});

