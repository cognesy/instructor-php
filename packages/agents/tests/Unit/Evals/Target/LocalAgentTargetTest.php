<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Target;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\EvalTracePolicy;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Tool\Tools\FakeTool;

it('preserves state across turns and isolates sessions', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('first', 'second')))
        ->build());

    $session = $target->open();
    expect($session->send('one')->reply())->toBe('first');
    expect($session->send('two')->reply())->toBe('second')
        ->and($session->run()->turns())->toBe(2)
        ->and($target->open()->run()->turns())->toBe(0);
});

it('projects tool executions and agent events from a controlled driver', function (): void {
    $continue = new CallableHook(static function (HookContext $context): HookContext {
        return $context->state()->stepCount() < 1
            ? $context->withState($context->state()->withExecutionContinued())
            : $context;
    });
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('lookup', ['id' => 'A1049']),
            ScenarioStep::final('Verified A1049'),
        )))
        ->withCapability(new UseHook($continue, HookTriggers::afterStep(), -200))
        ->build());

    $run = $target->open()->send('verify')->run();

    expect($run->succeeded())->toBeTrue()
        ->and($run->tools()->count())->toBe(1)
        ->and($run->tools()->all()[0]->name())->toBe('lookup')
        ->and($run->events()->count())->toBeGreaterThan(0);
});

it('defaults to a safe trace policy that digests tool payloads, and only serializes verbatim under an explicit full() policy', function (): void {
    $buildTarget = static fn (?EvalTracePolicy $policy) => LocalAgentTarget::fromFactory(
        static fn () => AgentBuilder::base()
            ->withCapability(new UseTools(new FakeTool('lookup', 'Lookup', static fn (string $id): string => "found {$id}")))
            ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
                ScenarioStep::toolCall('lookup', ['id' => 'A1049']),
                ScenarioStep::final('Verified A1049'),
            )))
            ->withCapability(new UseHook(
                new CallableHook(static function (HookContext $context): HookContext {
                    return $context->state()->stepCount() < 1
                        ? $context->withState($context->state()->withExecutionContinued())
                        : $context;
                }),
                HookTriggers::afterStep(),
                -200,
            ))
            ->build(),
        $policy,
    );

    $defaultRun = $buildTarget(null)->open()->send('verify')->run();
    $fullRun = $buildTarget(EvalTracePolicy::full())->open()->send('verify')->run();

    $defaultToolExecution = $defaultRun->toArray()['tools'][0];
    $fullToolExecution = $fullRun->toArray()['tools'][0];

    expect($defaultToolExecution['tool_call']['arguments'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($defaultToolExecution['result'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($fullToolExecution['tool_call']['arguments'])->toBe(['id' => 'A1049'])
        ->and($fullToolExecution['result'])->toBe('found A1049');
});
