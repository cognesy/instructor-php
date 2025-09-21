<?php declare(strict_types=1);

use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _noop_feat(): string { return 'ok'; }

it('stops due to token usage limit being reached', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '', toolCalls: new ToolCalls(new ToolCall('_noop_feat', [])), usage: new Usage(8, 1)),
        new InferenceResponse(content: 'final', usage: new Usage(2, 0)),
    ]);

    $tools = new Tools(FunctionTool::fromCallable(_noop_feat(...)));
        
    $state = new ToolUseState();
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        continuationCriteria: new ContinuationCriteria(new TokenUsageLimit(10, static fn(ToolUseState $state): int => $state->usage()->total())),
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    // first step accumulates to 9 -> can continue; after second step accumulates to 11 -> stop
    $state = $toolUse->nextStep($state);
    expect($toolUse->hasNextStep($state))->toBeTrue();
    $state = $toolUse->nextStep($state);
    expect($toolUse->hasNextStep($state))->toBeFalse();
});
