<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Addons\ToolUse\ContinuationCriteria\{StepsLimit,TokenUsageLimit,ExecutionTimeLimit,RetryLimit};
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _react_add(int $a, int $b): int { return $a + $b; }

it('runs a ReAct call then final answer', function () {
    $driver = new FakeInferenceDriver([
        // step 1: call tool
        new InferenceResponse(content: json_encode([
            'thought' => 'I will add numbers',
            'type' => 'call_tool',
            'tool' => '_react_add',
            'args' => ['a' => 2, 'b' => 3]
        ])),
        // step 2: final
        new InferenceResponse(content: json_encode([
            'thought' => 'I have the result',
            'type' => 'final_answer',
            'answer' => '5'
        ])),
    ]);

    $react = new ReActDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $criteria = new ContinuationCriteria(new StepsLimit(2), new TokenUsageLimit(8192), new ExecutionTimeLimit(30), new RetryLimit(1));
    
    $tools = new \Cognesy\Addons\ToolUse\Tools();
    $tools = $tools->withTool(FunctionTool::fromCallable(_react_add(...)));
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $state = $state->withMessages(\Cognesy\Messages\Messages::fromString('Add 2 and 3, then report the result'));
    
    $toolUse = new ToolUse(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->response())->toBe('5');
});

it('surfaces tool arg validation errors as observation', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: json_encode([
            'thought' => 'I will add numbers',
            'type' => 'call_tool',
            'tool' => '_react_add',
            'args' => ['a' => 2] // missing b
        ])),
        new InferenceResponse(content: json_encode([
            'thought' => 'I will stop now',
            'type' => 'final_answer',
            'answer' => 'error'
        ])),
    ]);

    $react = new ReActDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $criteria = new ContinuationCriteria(new StepsLimit(2), new TokenUsageLimit(8192), new ExecutionTimeLimit(30), new RetryLimit(0));
    
    $tools = new \Cognesy\Addons\ToolUse\Tools();
    $tools = $tools->withTool(FunctionTool::fromCallable(_react_add(...)));
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $state = $state->withMessages(\Cognesy\Messages\Messages::fromString('Add 2 and missing arg b'));
    
    $toolUse = new ToolUse(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->nextStep($state);
    expect($state->currentStep()->toolExecutions()->hasErrors())->toBeTrue();
    $all = $state->messages()->toArray();
    $joined = json_encode($all);
    expect($joined)->toContain('Observation');
    expect($joined)->toContain('ERROR');
});

it('can finalize via Inference when configured', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: json_encode([
            'thought' => 'I have the result already',
            'type' => 'final_answer',
            'answer' => 'stub'
        ])),
        // final via inference
        new InferenceResponse(content: 'The final answer is 42'),
    ]);

    $react = new ReActDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver), finalViaInference: true);
    $criteria = new ContinuationCriteria(new StepsLimit(1), new TokenUsageLimit(8192), new ExecutionTimeLimit(30), new RetryLimit(0));
    
    $tools = new \Cognesy\Addons\ToolUse\Tools();
    $state = new \Cognesy\Addons\ToolUse\Data\ToolUseState();
    $state = $state->withMessages(\Cognesy\Messages\Messages::fromString('Give me the final answer 42'));
    
    $toolUse = new ToolUse(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->response())->toContain('42');
});
