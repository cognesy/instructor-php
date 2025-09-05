<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _noop_feat(): string { return 'ok'; }

it('stops due to token usage limit being reached', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls([ new ToolCall('_noop_feat', []) ]), usage: new Usage(8, 1)),
        new InferenceResponse(content: 'final', usage: new Usage(2, 0)),
    ]);

    $toolUse = (new ToolUse(continuationCriteria: [ new TokenUsageLimit(10) ]))
        ->withDriver(new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver)))
        ->withTools([ \Cognesy\Addons\ToolUse\Tools\FunctionTool::fromCallable(_noop_feat(...)) ]);

    // first step accumulates to 9 -> can continue; after second step accumulates to 11 -> stop
    $toolUse->nextStep();
    expect($toolUse->hasNextStep())->toBeTrue();
    $toolUse->nextStep();
    expect($toolUse->hasNextStep())->toBeFalse();
});
