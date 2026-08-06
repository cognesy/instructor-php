<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Evals\AgentRun;
use Cognesy\Agents\Evals\EvalStep;
use Cognesy\Agents\Evals\EvalTracePolicy;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Tests\Support\LlmAwareFakeDriver;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use RuntimeException;

function continuesOnce(): CallableHook {
    return new CallableHook(static function (HookContext $context): HookContext {
        return $context->state()->stepCount() < 1
            ? $context->withState($context->state()->withExecutionContinued())
            : $context;
    });
}

it('projects a scripted tool-call step followed by a final-response step, in order', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('lookup', ['id' => 'A1049']),
            ScenarioStep::final('Verified A1049'),
        )))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('verify')->run();
    $steps = $run->steps()->all();

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->type())->toBe(AgentStepType::ToolExecution)
        ->and($steps[1]->type())->toBe(AgentStepType::FinalResponse)
        ->and($steps[0]->index())->toBe(0)
        ->and($steps[1]->index())->toBe(1)
        ->and($steps[0]->turn())->toBe(1)
        ->and($steps[1]->turn())->toBe(1)
        ->and($run->stepCount())->toBe(2);
});

it('exposes requested tool calls, executions, output, usage, errors, finish reason and stop signal via typed accessors', function (): void {
    $usage = new InferenceUsage(inputTokens: 42, outputTokens: 7);
    // A guard's stop signal (checked at the *next* iteration's beforeStep checkpoint)
    // lands on the execution, not on the already-completed StepExecution. To pin the
    // per-step accessor we need a signal set while the step itself is still current,
    // i.e. an afterStep hook - exactly the shape a custom stop condition would take.
    $stopAfterStep = new CallableHook(static fn (HookContext $context): HookContext => $context->withState(
        $context->state()->withStopSignal(new StopSignal(
            reason: StopReason::StopRequested,
            message: 'stop for test',
        )),
    ));

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('lookup', ['id' => 'A1049'], 'Looking it up', $usage),
        )))
        ->withCapability(new UseHook($stopAfterStep, HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('verify')->run();
    $step = $run->steps()->last();

    expect($step)->not->toBeNull();
    expect($step->requestedToolCalls()->first()?->name())->toBe('lookup')
        ->and($step->toolExecutions()->all())->toHaveCount(1)
        ->and($step->toolExecutions()->all()[0]->name())->toBe('lookup')
        ->and($step->toolExecutions()->all()[0]->hasError())->toBeFalse()
        ->and(trim($step->outputMessages()->toString()))->toBe('Looking it up')
        ->and($step->usage()->inputTokens)->toBe(42)
        ->and($step->usage()->outputTokens)->toBe(7)
        ->and($step->hasErrors())->toBeFalse()
        ->and($step->finishReason())->toBeInstanceOf(InferenceFinishReason::class)
        ->and($step->stopSignal()?->reason)->toBe(StopReason::StopRequested)
        ->and($run->stopSignal()?->reason)->toBe(StopReason::StopRequested)
        ->and($run->usage()->inputTokens)->toBe(42)
        ->and($run->usage()->outputTokens)->toBe(7)
        ->and($run->duration())->toBeGreaterThanOrEqual(0.0);
});

it('preserves tool name, call order and error flags through digesting', function (): void {
    $toolCalls = ToolCalls::empty()
        ->withAddedToolCall('lookup', ['id' => 'A1049'])
        ->withAddedToolCall('explode', ['id' => 'A1049']);
    $toolStep = new ScenarioStep(
        response: '',
        usage: new InferenceUsage(0, 0),
        stepType: AgentStepType::ToolExecution,
        toolCalls: $toolCalls,
        executeTools: true,
    );

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(
            new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}"),
            new FakeTool('explode', 'Explode', static function (string $id): never {
                throw new RuntimeException('boom');
            }),
        ))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('go')->run();
    $executions = $run->steps()->all()[0]->toArray()['toolExecutions'];

    expect($executions)->toHaveCount(2)
        ->and($executions[0]['name'])->toBe('lookup')
        ->and($executions[1]['name'])->toBe('explode')
        ->and($executions[0]['hasError'])->toBeFalse()
        ->and($executions[1]['hasError'])->toBeTrue()
        // The error MESSAGE is digested, not preserved verbatim - see the
        // dedicated secret-in-error-message test below for why.
        ->and($executions[1]['error'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($executions[1]['error']['preview'])->not->toContain('boom')
        ->and($executions[0]['result'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($executions[1]['result'])->toBeNull();
});

it('digests a tool argument and a tool result so a padded secret never reaches the serialized trace', function (): void {
    $secret = 'SECRET-CREDIT-CARD-4111111111111111';
    $argPadding = str_repeat('A', 200);
    $resultPadding = str_repeat('B', 200);

    $toolStep = ScenarioStep::toolCall('lookup', ['payload' => $argPadding . $secret]);

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool(
            'lookup',
            'Lookup',
            static fn (string $payload): string => $resultPadding . $secret,
        )))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('go')->run();
    $runArray = $run->toArray();
    $encoded = json_encode($runArray, JSON_THROW_ON_ERROR);

    $toolStepArray = $run->steps()->all()[0]->toArray();
    $argumentDigest = $toolStepArray['requestedToolCalls'][0]['arguments'];
    $resultDigest = $toolStepArray['toolExecutions'][0]['result'];

    expect($encoded)->not->toContain($secret);
    expect($argumentDigest)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($argumentDigest['hash'])->toStartWith('sha256:')
        ->and($argumentDigest['bytes'])->toBeGreaterThan(120)
        ->and(strlen($argumentDigest['preview']))->toBeLessThanOrEqual(120)
        ->and($argumentDigest['preview'])->not->toContain($secret);
    expect($resultDigest)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($resultDigest['bytes'])->toBeGreaterThan(120)
        ->and($resultDigest['preview'])->not->toContain($secret);
});

it('digests a failing tool\'s exception message so a secret embedded in it never reaches the trace, at both the AgentRun and EvalStep projection sites', function (): void {
    // The test above exercises a SUCCESSFUL call, where the secret lives in
    // args/result - exactly the blind spot that let a raw exception message
    // through undigested (exception text routinely embeds the offending
    // input, e.g. "Invalid card number 4111...", "HTTP 401 for ...?key=sk-...").
    $secret = 'SECRET-CREDIT-CARD-4111111111111111';
    $messagePadding = str_repeat('C', 200);

    $toolStep = ScenarioStep::toolCall('lookup', ['id' => 'A1049']);

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool(
            'lookup',
            'Lookup',
            static function (string $id) use ($messagePadding, $secret): never {
                throw new RuntimeException($messagePadding . $secret);
            },
        )))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('go')->run();
    $encoded = json_encode($run->toArray(), JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain($secret);

    // AgentRun.php:226's own `tools` projection.
    $runToolExecution = $run->toArray()['tools'][0];
    expect($runToolExecution['error'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($runToolExecution['error']['hash'])->toStartWith('sha256:')
        ->and($runToolExecution['error']['bytes'])->toBeGreaterThan(120)
        ->and($runToolExecution['error']['preview'])->not->toContain($secret);

    // EvalStep.php:244's own `toolExecutions` projection.
    $stepToolExecution = $run->steps()->all()[0]->toArray()['toolExecutions'][0];
    expect($stepToolExecution['error'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($stepToolExecution['error']['hash'])->toStartWith('sha256:')
        ->and($stepToolExecution['error']['bytes'])->toBeGreaterThan(120)
        ->and($stepToolExecution['error']['preview'])->not->toContain($secret);

    // Two more sites that turned out to carry the identical exception message
    // and were not part of the originally reported pair, found only by actually
    // reproducing a failing tool rather than reasoning from the source: the
    // run-level joined `errors` string (AgentRun.php) and the step-level
    // `errors[].message` list (EvalStep.php) both re-embed the same text.
    $runErrors = $run->toArray()['errors'];
    expect($runErrors)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($runErrors['preview'])->not->toContain($secret);

    $stepErrors = $run->steps()->all()[0]->toArray()['errors'][0];
    expect($stepErrors['message'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($stepErrors['message']['preview'])->not->toContain($secret)
        ->and($stepErrors['class'])->toBe(ToolExecutionException::class);
});

it('serializes a failing tool\'s error message verbatim only under an explicitly constructed full() policy, matching how arguments and results already behave', function (): void {
    $secret = 'SECRET-CREDIT-CARD-4111111111111111';
    $toolStep = ScenarioStep::toolCall('lookup', ['id' => 'A1049']);

    $buildTarget = static fn (?EvalTracePolicy $policy) => LocalAgentTarget::fromFactory(
        static fn () => AgentBuilder::base()
            ->withCapability(new UseTools(new FakeTool(
                'lookup',
                'Lookup',
                static function (string $id) use ($secret): never {
                    throw new RuntimeException('Invalid id: ' . $secret);
                },
            )))
            ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
            ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
            ->build(),
        $policy,
    );

    $safeRun = $buildTarget(null)->open()->send('go')->run();
    expect(json_encode($safeRun->toArray(), JSON_THROW_ON_ERROR))->not->toContain($secret);
    expect($safeRun->toArray()['tools'][0]['error'])->toHaveKeys(['hash', 'bytes', 'preview']);
    expect($safeRun->steps()->all()[0]->toArray()['toolExecutions'][0]['error'])->toHaveKeys(['hash', 'bytes', 'preview']);
    expect($safeRun->toArray()['errors'])->toHaveKeys(['hash', 'bytes', 'preview']);

    $fullRun = $buildTarget(EvalTracePolicy::full())->open()->send('go')->run();
    expect(json_encode($fullRun->toArray(), JSON_THROW_ON_ERROR))->toContain($secret);
    expect($fullRun->toArray()['tools'][0]['error'])->toContain($secret);
    expect($fullRun->steps()->all()[0]->toArray()['toolExecutions'][0]['error'])->toContain($secret);
    expect($fullRun->toArray()['errors'])->toContain($secret);
});

it('digests an unpadded short secret so it never reaches the serialized trace, with no padding required', function (): void {
    // Unlike the padded-secret test above, this secret alone is far under the
    // 120-byte preview window - the exact case a byte-bounded (non-shape) preview
    // would have leaked in full. safe() must redact it regardless of size.
    $secret = 'SECRET-4111111111111111';

    $toolStep = ScenarioStep::toolCall('lookup', ['card' => $secret]);

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool(
            'lookup',
            'Lookup',
            static fn (string $card): string => $card,
        )))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('go')->run();
    $encoded = json_encode($run->toArray(), JSON_THROW_ON_ERROR);

    $toolStepArray = $run->steps()->all()[0]->toArray();
    $argumentDigest = $toolStepArray['requestedToolCalls'][0]['arguments'];
    $resultDigest = $toolStepArray['toolExecutions'][0]['result'];

    expect($encoded)->not->toContain($secret);
    expect($argumentDigest)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($argumentDigest['preview'])->toBe('{"card":"<string:23>"}')
        ->and($argumentDigest['preview'])->not->toContain($secret);
    expect($resultDigest)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($resultDigest['preview'])->toBe('"<string:23>"')
        ->and($resultDigest['preview'])->not->toContain($secret);
});

it('serializes tool payloads verbatim only under an explicitly constructed full() policy, never by default', function (): void {
    $value = 'not-so-secret-value';
    $toolStep = ScenarioStep::toolCall('lookup', ['payload' => $value]);

    $buildTarget = static fn (?EvalTracePolicy $policy) => LocalAgentTarget::fromFactory(
        static fn () => AgentBuilder::base()
            ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $payload): string => $value)))
            ->withCapability(new UseDriver(FakeAgentDriver::fromSteps($toolStep, ScenarioStep::final('done'))))
            ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
            ->build(),
        $policy,
    );

    $defaultRun = $buildTarget(null)->open()->send('go')->run();
    $safeArguments = $defaultRun->steps()->all()[0]->toArray()['requestedToolCalls'][0]['arguments'];
    expect($safeArguments)->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($safeArguments)->not->toHaveKey('payload');

    $fullRun = $buildTarget(EvalTracePolicy::full())->open()->send('go')->run();
    $fullArguments = $fullRun->steps()->all()[0]->toArray()['requestedToolCalls'][0]['arguments'];
    expect($fullArguments)->toBe(['payload' => $value]);

    expect(EvalTracePolicy::safe()->isFull())->toBeFalse()
        ->and(EvalTracePolicy::full()->isFull())->toBeTrue();
});

it('has no inputMessages accessor and never serializes input messages or raw response data', function (): void {
    expect(method_exists(EvalStep::class, 'inputMessages'))->toBeFalse()
        ->and(method_exists(EvalStep::class, 'inferenceResponse'))->toBeFalse();

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('lookup', ['id' => 'A1049']),
            ScenarioStep::final('Verified A1049'),
        )))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('verify')->run();
    $encoded = json_encode($run->toArray(), JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('inputMessages')
        ->and($encoded)->not->toContain('inferenceResponse')
        ->and($encoded)->not->toContain('responseData')
        ->and($encoded)->not->toContain('reasoningContent');
});

it('accumulates steps across three turns without double counting and reports the last turn\'s stop signal', function (): void {
    $labelStop = new CallableHook(static function (HookContext $context): HookContext {
        $state = $context->state();
        $reply = trim($state->currentStepOrLast()?->outputMessages()->toString() ?? '');
        return $context->withState($state->withStopSignal(new StopSignal(
            reason: StopReason::UserRequested,
            message: "stopped-after-{$reply}",
        )));
    });

    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('first', 'second', 'third')))
        ->withCapability(new UseHook($labelStop, HookTriggers::afterStep(), -200))
        ->build());

    $session = $target->open();
    $session->send('one');
    $session->send('two');
    $run = $session->send('three')->run();

    expect($run->turns())->toBe(3)
        ->and($run->stepCount())->toBe(3);

    $steps = $run->steps()->all();
    expect($steps[0]->turn())->toBe(1)
        ->and($steps[1]->turn())->toBe(2)
        ->and($steps[2]->turn())->toBe(3)
        ->and($steps[0]->index())->toBe(0)
        ->and($steps[1]->index())->toBe(1)
        ->and($steps[2]->index())->toBe(2);

    expect($steps[0]->stopSignal()?->message)->toBe('stopped-after-first')
        ->and($steps[1]->stopSignal()?->message)->toBe('stopped-after-second')
        ->and($steps[2]->stopSignal()?->message)->toBe('stopped-after-third')
        ->and($run->stopSignal()?->message)->toBe('stopped-after-third');
});

it('round-trips AgentRun::fromArray(AgentRun::toArray()) preserving step count, order, tool names and turn stamps', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('lookup', ['id' => 'A1049']),
            ScenarioStep::final('Verified A1049'),
        )))
        ->withCapability(new UseHook(continuesOnce(), HookTriggers::afterStep(), -200))
        ->build());

    $session = $target->open();
    $session->send('one');
    $original = $session->send('two')->run();

    $roundTripped = AgentRun::fromArray($original->toArray());

    expect($roundTripped->stepCount())->toBe($original->stepCount())
        ->and($roundTripped->turns())->toBe($original->turns());

    $originalSteps = $original->steps()->all();
    $roundTrippedSteps = $roundTripped->steps()->all();
    foreach ($originalSteps as $i => $step) {
        $names = array_map(
            static fn ($execution): string => $execution->name(),
            $step->toolExecutions()->all(),
        );
        $roundTrippedNames = array_map(
            static fn ($execution): string => $execution->name(),
            $roundTrippedSteps[$i]->toolExecutions()->all(),
        );
        expect($roundTrippedNames)->toBe($names)
            ->and($roundTrippedSteps[$i]->turn())->toBe($step->turn())
            ->and($roundTrippedSteps[$i]->index())->toBe($step->index())
            ->and($roundTrippedSteps[$i]->type())->toBe($step->type());
    }
});

it('leaves llmProfile null when the driver never resolves an LLMConfig', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))
        ->build());

    $run = $target->open()->send('hi')->run();

    expect($run->llmProfile())->toBeNull();
});

it('carries the resolved LLMConfig into llmProfile(), and preserves it across turns and a toArray()/fromArray() round trip', function (): void {
    $config = new LLMConfig(model: 'gpt-5', maxTokens: 2048, contextLength: 128_000, maxOutputLength: 8192, driver: 'openai');
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(new LlmAwareFakeDriver(FakeAgentDriver::fromResponses('first', 'second'), $config)))
        ->build());

    $session = $target->open();
    $session->send('one');
    $run = $session->send('two')->run();

    expect($run->llmProfile())->not->toBeNull()
        ->and($run->llmProfile()?->driver)->toBe('openai')
        ->and($run->llmProfile()?->model)->toBe('gpt-5')
        ->and($run->toArray()['llmProfile'])->toBe([
            'driver' => 'openai',
            'model' => 'gpt-5',
            'maxTokens' => 2048,
            'contextLength' => 128_000,
            'maxOutputLength' => 8192,
        ]);

    $roundTripped = AgentRun::fromArray($run->toArray());
    expect($roundTripped->llmProfile()?->model)->toBe('gpt-5');
});
