<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function add_numbers(int $a, int $b): int { return $a + $b; }
function subtract_numbers(int $a, int $b): int { return $a - $b; }

it('executes a tool call and builds follow-up messages', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls([ new ToolCall('add_numbers', ['a' => 2, 'b' => 3]) ])
        ),
    ]);

    $tools = (new Tools())
        ->withTool(FunctionTool::fromCallable(add_numbers(...)))
        ->withTool(FunctionTool::fromCallable(subtract_numbers(...)));
        
    $state = (new ToolUseState())
        ->withMessages(Messages::fromString('Add numbers'));
        
    $toolUse = new ToolUse(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $newState = $toolUse->nextStep($state);
    $step = $newState->currentStep();

    expect($step?->hasToolCalls())->toBeTrue();
    expect(count($step?->toolExecutions()->all()))->toBe(1);
    expect($step?->messages()->count())->toBeGreaterThan(0);
});

it('iterates until no more tool calls and returns final response', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls([ new ToolCall('add_numbers', ['a' => 2, 'b' => 3]) ])
        ),
        new InferenceResponse(content: '5'),
    ]);

    $tools = (new Tools())
        ->withTool(FunctionTool::fromCallable(add_numbers(...)));
        
    $state = (new ToolUseState())
        ->withMessages(Messages::fromString('Add then report the result'));
        
    $toolUse = new ToolUse(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $finalState = $toolUse->finalStep($state);
    expect($finalState->currentStep()->response())->toBe('5');
    expect($finalState->stepCount())->toBeGreaterThan(0);
});
