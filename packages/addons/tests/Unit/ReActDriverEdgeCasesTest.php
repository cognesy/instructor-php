<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
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

    $react = new ReActDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $toolUse = (new ToolUse(continuationCriteria: [ new StepsLimit(2) ]))
        ->withDriver($react)
        ->withTools([ FunctionTool::fromCallable(_noop(...)) ])
        ->withMessages(Messages::empty());

    $first = $toolUse->nextStep();
    expect($toolUse->state()->variable('react_last_decision_type'))->toBe('call_tool');

    $final = $toolUse->finalStep();
    expect($toolUse->state()->variable('react_last_decision_type'))->toBe('final_answer');
    expect($final->response())->toBe('done');
});

it('surfaces extraction failure as validation exception (deterministic)', function () {
    // malformed JSON to trigger failure inside StructuredOutput path
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{bad json'),
    ]);

    $react = new ReActDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $toolUse = (new ToolUse)
        ->withDriver($react)
        ->withTools([ FunctionTool::fromCallable(_noop(...)) ]);

    expect(fn() => $toolUse->nextStep())
        ->toThrow(\Cognesy\Instructor\Validation\Exceptions\ValidationException::class);
});
