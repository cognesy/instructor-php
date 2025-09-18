<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _inc_lb(int $x): int { return $x + 1; }

it('hasNextStep is true when no current step', function () {
    $tools = new Tools();
    $state = new ToolUseState();
    $tu = ToolUseFactory::default(tools: $tools);
    expect($tu->hasNextStep($state))->toBeTrue();
});

it('finalStep respects StepsLimit(1)', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_inc_lb', ['x' => 1]) ])),
    ]);
    $tools = (new Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new ToolUseState();
        
    $toolUse = ToolUseFactory::default(
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

    $tools = (new Tools())
        ->withTool(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new ToolUseState();
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $state = $toolUse->nextStep($state);
    $state = $toolUse->finalStep($state);

    expect($state->usage()->toArray())->toMatchArray(['input' => 6, 'output' => 8]);
});

