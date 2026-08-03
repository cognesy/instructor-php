<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Core;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Tests\Support\FakeInferenceDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\LLMProvider;

it('applies inference hooks at the real provider boundary and emits post-hook events', function () {
    $events = new EventDispatcher();
    $log = [];
    $requestEvent = null;
    $responseEvent = null;
    $seenRequestMessages = null;

    $events->addListener(InferenceRequestStarted::class, function (InferenceRequestStarted $event) use (&$log, &$requestEvent): void {
        $log[] = 'request_event';
        $requestEvent = $event;
    });
    $events->addListener(InferenceResponseReceived::class, function (InferenceResponseReceived $event) use (&$log, &$responseEvent): void {
        $log[] = 'response_event';
        $responseEvent = $event;
    });

    $inference = new FakeInferenceDriver([
        new InferenceResponse(
            content: 'provider response',
            finishReason: 'stop',
            usage: new InferenceUsage(inputTokens: 1, outputTokens: 2),
        ),
    ]);
    $hook = new CallableHook(function (HookContext $context) use (&$log, &$seenRequestMessages): HookContext {
        return match ($context->triggerType()) {
            HookTrigger::BeforeInferenceRequest => (function () use ($context, &$log, &$seenRequestMessages): HookContext {
                $log[] = 'before_hook';
                $seenRequestMessages = $context->inferenceRequest()?->messages()->toArray();
                return $context
                    ->withState($context->state()->withMetadata('before_hook', true))
                    ->withInferenceRequest($context->inferenceRequest()?->withModel('hook-model'));
            })(),
            HookTrigger::AfterInferenceResponse => (function () use ($context, &$log): HookContext {
                $log[] = 'after_hook';
                return $context
                    ->withState($context->state()->withMetadata('after_hook', true))
                    ->withInferenceResponse($context->inferenceResponse()?->with(
                        content: 'hook response',
                        finishReason: 'length',
                        usage: new InferenceUsage(inputTokens: 3, outputTokens: 7),
                    ));
            })(),
            default => $context,
        };
    });

    $agent = AgentBuilder::base($events)
        ->withCapability(new UseLLMConfig(LLMProvider::new()->withDriver($inference)))
        ->withCapability(new UseHook(
            hook: $hook,
            triggers: HookTriggers::of(
                HookTrigger::BeforeInferenceRequest,
                HookTrigger::AfterInferenceResponse,
            ),
        ))
        ->build();

    $final = $agent->execute(AgentState::empty()->withMessages(Messages::fromString('inspect this')));
    $step = $final->steps()->lastStep();

    expect($inference->requests)->toHaveCount(1)
        ->and($seenRequestMessages)->toBe($inference->requests[0]->messages()->toArray())
        ->and($inference->requests[0]->model())->toBe('hook-model')
        ->and($step?->inputMessages()->toArray())->toBe($inference->requests[0]->messages()->toArray())
        ->and($step?->inferenceResponse()->content())->toBe('hook response')
        ->and($final->metadata()->get('before_hook'))->toBeTrue()
        ->and($final->metadata()->get('after_hook'))->toBeTrue()
        ->and($log)->toBe(['before_hook', 'request_event', 'after_hook', 'response_event'])
        ->and($requestEvent)->toBeInstanceOf(InferenceRequestStarted::class)
        ->and($requestEvent?->model)->toBe('hook-model')
        ->and($requestEvent?->inferenceExecutionId)->not->toBeNull()->not->toBe('')
        ->and($responseEvent)->toBeInstanceOf(InferenceResponseReceived::class)
        ->and($responseEvent?->finishReason)->toBe('length')
        ->and($responseEvent?->usage?->outputTokens)->toBe(7)
        ->and($responseEvent?->inferenceExecutionId)->toBe($requestEvent?->inferenceExecutionId)
        ->and($responseEvent?->executionId)->toBe($requestEvent?->executionId);
});

it('rebinds a replacement loop interceptor before inference', function () {
    $initialCalls = 0;
    $replacementCalls = 0;
    $inference = new FakeInferenceDriver([new InferenceResponse(content: 'done')]);
    $initial = new CallableHook(function (HookContext $context) use (&$initialCalls): HookContext {
        $initialCalls++;
        return $context->withInferenceRequest($context->inferenceRequest()?->withModel('initial-model'));
    });
    $replacement = new CallableHook(function (HookContext $context) use (&$replacementCalls): HookContext {
        $replacementCalls++;
        return $context->withInferenceRequest($context->inferenceRequest()?->withModel('replacement-model'));
    });

    $agent = AgentBuilder::base()
        ->withCapability(new UseLLMConfig(LLMProvider::new()->withDriver($inference)))
        ->withCapability(new UseHook($initial, HookTriggers::beforeInferenceRequest()))
        ->build()
        ->withInterceptor(
            (new HookStack(new RegisteredHooks()))
                ->with($replacement, HookTriggers::beforeInferenceRequest()),
        );

    $agent->execute(AgentState::empty()->withMessages(Messages::fromString('run')));

    expect($initialCalls)->toBe(0)
        ->and($replacementCalls)->toBe(1)
        ->and($inference->requests[0]->model())->toBe('replacement-model');
});

it('keeps drivers without lifecycle interceptor support executable', function () {
    $inferenceHookCalls = 0;
    $driver = new FakeAgentDriver([
        ScenarioStep::final('custom driver response'),
    ]);
    $hook = new CallableHook(function (HookContext $context) use (&$inferenceHookCalls): HookContext {
        $inferenceHookCalls++;
        return $context;
    });

    $final = AgentBuilder::base()
        ->withCapability(new UseDriver($driver))
        ->withCapability(new UseHook($hook, HookTriggers::beforeInferenceRequest()))
        ->build()
        ->execute(AgentState::empty()->withMessages(Messages::fromString('run')));

    expect($inferenceHookCalls)->toBe(0)
        ->and(trim($final->steps()->lastStep()?->outputMessages()->toString() ?? ''))->toBe('custom driver response');
});
