<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _noop(): string { return 'ok'; }

it('sets react_last_decision_type for call_tool and final_answer', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: json_encode([
            'thought' => 'x', 'type' => 'call_tool', 'tool' => '_noop', 'args' => []
        ])),
        new InferenceResponse(content: json_encode([
            'thought' => 'y', 'type' => 'final_answer', 'answer' => 'done'
        ])),
    ]);

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
    
    $tools = new \Cognesy\Addons\ToolUse\Tools();
    $tools = $tools->withTool(FunctionTool::fromCallable(_noop(...)));
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $state = $state->withMessages(Messages::empty());
    
    $toolUse = new ToolUse(tools: $tools, continuationCriteria: new ContinuationCriteria(new StepsLimit(2)), driver: $react);

    $state = $toolUse->nextStep($state);
    expect($state->currentStep()->stepType())->toBe(StepType::ToolExecution);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->stepType())->toBe(StepType::FinalResponse);
    expect($state->currentStep()->response())->toBe('done');
});

it('surfaces extraction failure as validation exception (deterministic)', function () {
    // malformed JSON to trigger failure inside StructuredOutput path
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{bad json'),
    ]);

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
    $tools = (new \Cognesy\Addons\ToolUse\Tools())->withTool(FunctionTool::fromCallable(_noop(...)));
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $toolUse = new ToolUse(tools: $tools, driver: $react);

    expect(fn() => $toolUse->nextStep($state))
        ->toThrow(\Cognesy\Instructor\Validation\Exceptions\ValidationException::class);
});
