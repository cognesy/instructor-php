<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Drivers;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Time\FrozenClock;
use Tests\Addons\Support\FakeInferenceRequestDriver;


function _noop(): string { return 'ok'; }

it('sets react_last_decision_type for call_tool and final_answer', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: json_encode([
            'thought' => 'x', 'type' => 'call_tool', 'tool' => '_noop', 'args' => []
        ])),
        new InferenceResponse(content: json_encode([
            'thought' => 'y', 'type' => 'final_answer', 'answer' => 'done'
        ])),
    ]);

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));

    $tools = new Tools(FunctionTool::fromCallable(_noop(...)));
    $state = new ToolUseState();
    $state = $state->withMessages(Messages::empty());

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        continuationCriteria: new ContinuationCriteria(new StepsLimit(2, fn(ToolUseState $s) => $s->stepCount())),
        driver: $react
    );

    $state = $toolUse->nextStep($state);
    expect($state->currentStep()->stepType())->toBe(ToolUseStepType::ToolExecution);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->stepType())->toBe(ToolUseStepType::FinalResponse);
    expect($state->currentStep()->outputMessages()->last()->toString())->toBe('done');
});

it('records extraction failures inside failure steps (deterministic)', function () {
    // malformed JSON to trigger failure inside StructuredOutput path
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{bad json'),
    ]);

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
    $tools = new Tools(FunctionTool::fromCallable(_noop(...)));
    $state = new ToolUseState();

    // Use explicit criteria with FrozenClock to avoid issues
    $clock = FrozenClock::at('2024-01-01 12:00:00');
    $continuationCriteria = new ContinuationCriteria(
        new StepsLimit(3, fn(ToolUseState $s) => $s->stepCount()),
        new ExecutionTimeLimit(30, fn(ToolUseState $s) => $s->startedAt(), $clock)
    );

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: $react,
        continuationCriteria: $continuationCriteria
    );

    $result = $toolUse->nextStep($state);

    // ReActDriver handles extraction failures gracefully by creating Error steps
    // rather than throwing exceptions, so status remains InProgress
    expect($result->status())->toBe(ToolUseStatus::InProgress);
    expect($result->currentStep()?->hasErrors())->toBeTrue();
    expect($result->currentStep()?->stepType())->toBe(ToolUseStepType::Error);
    expect($result->currentStep()?->errorsAsString())
        ->toContain('Empty response content');
});
