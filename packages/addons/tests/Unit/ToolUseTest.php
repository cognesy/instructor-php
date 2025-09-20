<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
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
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $newState = $toolUse->nextStep($state);
    $step = $newState->currentStep();

    expect($step?->hasToolCalls())->toBeTrue();
    expect(count($step?->toolExecutions()->all()))->toBe(1);
    expect($step?->inputMessages()->count())->toBeGreaterThan(0);
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
        
    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $finalState = $toolUse->finalStep($state);
    expect($finalState->currentStep()->outputMessages()->last()->toString())->toBe('5');
    expect($finalState->stepCount())->toBeGreaterThan(0);
});

it('separates context and transcript messages between input and output collections', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls([ new ToolCall('add_numbers', ['a' => 2, 'b' => 3]) ])
        ),
        new InferenceResponse(content: '5'),
    ]);

    $tools = (new Tools())
        ->withTool(FunctionTool::fromCallable(add_numbers(...)));

    $initialMessages = Messages::fromArray([
        ['role' => 'system', 'content' => 'Be precise and always explain your reasoning.'],
        ['role' => 'user', 'content' => 'Add 2 and 3, then report the result.'],
    ]);

    $state = (new ToolUseState())->withMessages($initialMessages);

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $intermediateState = $toolUse->nextStep($state);
    $step = $intermediateState->currentStep();

    expect($step)->not->toBeNull();
    expect($step?->inputMessages()->toArray())->toBe($initialMessages->toArray());
    expect($step?->outputMessages()->count())->toBe(3);
    expect($step?->outputMessages()->first()->role()->value)->toBe('assistant');
    expect($step?->outputMessages()->all()[1]->role()->value)->toBe('tool');
    expect($step?->outputMessages()->last()->role()->value)->toBe('assistant');
    expect($step?->outputMessages()->last()->toString())->toBe('');

    $finalState = $toolUse->finalStep($intermediateState);
    $finalStep = $finalState->currentStep();

    $expectedContext = $intermediateState->messages();

    expect($finalStep)->not->toBeNull();
    expect($finalStep?->inputMessages()->toArray())->toBe($expectedContext->toArray());
    expect($finalStep?->outputMessages()->count())->toBe(1);
    expect($finalStep?->outputMessages()->last()->role()->value)->toBe('assistant');
    expect($finalStep?->outputMessages()->last()->toString())->toBe('5');
});

it('round-trips message collections through ToolUseStep serialization', function () {
    $input = Messages::fromArray([
        ['role' => 'system', 'content' => 'Follow the instructions carefully.'],
        ['role' => 'user', 'content' => 'Describe the weather.'],
    ]);

    $output = Messages::fromArray([
        ['role' => 'assistant', 'content' => 'Invoking tool...'],
        ['role' => 'tool', 'content' => 'Sunny with a chance of rain'],
    ]);

    $step = new ToolUseStep(
        inputMessages: $input,
        outputMessages: $output,
    );

    $serialized = $step->toArray();
    $hydrated = ToolUseStep::fromArray($serialized);

    expect($hydrated->inputMessages()->toArray())->toBe($input->toArray());
    expect($hydrated->outputMessages()->toArray())->toBe($output->toArray());
    expect($hydrated->outputMessages()->count())->toBe($output->count());
});
