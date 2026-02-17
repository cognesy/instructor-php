<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceRequestDriver;


function _inc_lb(int $x): int { return $x + 1; }

it('hasNextStep is true when no current step', function () {
    $tools = new Tools();
    $state = new ToolUseState();
    $tu = ToolUseFactory::default(tools: $tools);
    expect($tu->hasNextStep($state))->toBeTrue();
});

it('hasNextStep stops when criteria block first step', function () {
    $tools = new Tools();
    $state = new ToolUseState();
    $tu = ToolUseFactory::default(
        tools: $tools,
        continuationCriteria: new ContinuationCriteria(new StepsLimit(0, static fn(ToolUseState $state): int => $state->stepCount()))
    );

    expect($tu->hasNextStep($state))->toBeFalse();
    expect($tu->finalStep($state))->toBe($state);
});

it('finalStep respects StepsLimit(1)', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls(new ToolCall('_inc_lb', ['x' => 1]))),
    ]);
    $tools = new Tools(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new ToolUseState();
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        continuationCriteria: new ContinuationCriteria(new StepsLimit(1, static fn(ToolUseState $state): int => $state->stepCount())),
        driver: new ToolCallingDriver(
            inference: InferenceRuntime::fromProvider(
                provider: LLMProvider::new()->withDriver($driver),
            ),
        )
    );

    $state = $toolUse->finalStep($state);
    expect($state->stepCount())->toBe(1);
});

it('accumulates usage across steps', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls(new ToolCall('_inc_lb', ['x' => 1])), usage: new Usage(2,3)),
        new InferenceResponse(content: 'ok', usage: new Usage(4,5)),
    ]);

    $tools = new Tools(FunctionTool::fromCallable(_inc_lb(...)));
        
    $state = new ToolUseState();
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(
            inference: InferenceRuntime::fromProvider(
                provider: LLMProvider::new()->withDriver($driver),
            ),
        )
    );

    $state = $toolUse->nextStep($state);
    $state = $toolUse->finalStep($state);

    expect($state->usage()->toArray())->toMatchArray(['input' => 6, 'output' => 8]);
});
