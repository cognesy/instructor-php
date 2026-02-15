<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Drivers;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Collections\ToolUseSteps;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceRequestDriver;


function _react_add(int $a, int $b): int { return $a + $b; }

it('runs a ReAct call then final answer', function () {
    $driver = new FakeInferenceRequestDriver([
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

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
    $criteria = new ContinuationCriteria(
        new StepsLimit(2, fn(ToolUseState $s): int => $s->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $s): int => $s->usage()->total()),
        new ExecutionTimeLimit(30, fn(ToolUseState $s): \DateTimeImmutable => $s->startedAt()),
        new RetryLimit(1, fn(ToolUseState $s): ToolUseSteps => $s->steps(), fn(ToolUseStep $s): bool => $s->hasErrors())
    );
    
    $tools = new Tools(FunctionTool::fromCallable(_react_add(...)));
    $state = new ToolUseState();
    $state = $state->withMessages(Messages::fromString('Add 2 and 3, then report the result'));
    
    $toolUse = ToolUseFactory::default(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->outputMessages()->last()->toString())->toBe('5');
});

it('surfaces tool arg validation errors as observation', function () {
    $driver = new FakeInferenceRequestDriver([
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

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));
    $criteria = new ContinuationCriteria(
        new StepsLimit(2, fn(ToolUseState $s): int => $s->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $s): int => $s->usage()->total()),
        new ExecutionTimeLimit(30, fn(ToolUseState $s): \DateTimeImmutable => $s->startedAt()),
        new RetryLimit(0, fn(ToolUseState $s): ToolUseSteps => $s->steps(), fn(ToolUseStep $s): bool => $s->hasErrors())
    );
    
    $tools = new Tools();
    $tools = $tools->withTool(FunctionTool::fromCallable(_react_add(...)));
    $state = new ToolUseState();
    $state = $state->withMessages(Messages::fromString('Add 2 and missing arg b'));
    
    $toolUse = ToolUseFactory::default(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->nextStep($state);
    expect($state->currentStep()->toolExecutions()->hasErrors())->toBeTrue();
    $all = $state->messages()->toArray();
    $joined = json_encode($all);
    expect($joined)->toContain('Observation');
    expect($joined)->toContain('ERROR');
});

it('can finalize via Inference when configured', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: json_encode([
            'thought' => 'I have the result already',
            'type' => 'final_answer',
            'answer' => 'stub'
        ])),
        // final via inference
        new InferenceResponse(content: 'The final answer is 42'),
    ]);

    $react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver), finalViaInference: true);
    $criteria = new ContinuationCriteria(
        new StepsLimit(1, fn(ToolUseState $s): int => $s->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $s): int => $s->usage()->total()),
        new ExecutionTimeLimit(30, fn(ToolUseState $s): \DateTimeImmutable => $s->startedAt()),
        new RetryLimit(0, fn(ToolUseState $s): ToolUseSteps => $s->steps(), fn(ToolUseStep $s): bool => $s->hasErrors())
    );
    
    $tools = new Tools();
    $state = new ToolUseState();
    $state = $state->withMessages(Messages::fromString('Give me the final answer 42'));
    
    $toolUse = ToolUseFactory::default(tools: $tools, continuationCriteria: $criteria, driver: $react);

    $state = $toolUse->finalStep($state);
    expect($state->currentStep()->outputMessages()->last()->toString())->toContain('42');
});
