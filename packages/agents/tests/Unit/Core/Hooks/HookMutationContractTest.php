<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Events\HookContractViolated;
use Cognesy\Agents\Exceptions\HookContractViolationException;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Exceptions\ErrorList;

function hookContractContexts(): array {
    $state = AgentState::empty();
    $call = ToolCall::fromArray(['id' => 'call-1', 'name' => 'demo', 'arguments' => '{}']);
    $execution = ToolExecution::blocked($call, 'fixture');
    $request = new InferenceRequest(messages: Messages::fromString('request'));
    $response = new InferenceResponse(content: 'response');

    return [
        HookContext::beforeExecution($state),
        HookContext::beforeStep($state),
        HookContext::beforeInferenceRequest($state, $request),
        HookContext::afterInferenceResponse($state, $request, $response),
        HookContext::beforeToolUse($state, $call),
        HookContext::afterToolUse($state, $execution),
        HookContext::afterStep($state),
        HookContext::onStop($state),
        HookContext::afterExecution($state),
        HookContext::onError($state, ErrorList::fromErrors(new RuntimeException('fixture'))),
    ];
}

it('defines the explicit mutable fields for every lifecycle trigger', function () {
    expect(array_map(
        static fn (HookTrigger $trigger): array => [$trigger->value, $trigger->mutableFields()],
        HookTrigger::cases(),
    ))->toBe([
        ['before_execution', ['state', 'metadata']],
        ['before_step', ['state', 'metadata']],
        ['before_inference_request', ['state', 'inferenceRequest', 'metadata']],
        ['after_inference_response', ['state', 'inferenceResponse', 'metadata']],
        ['before_tool_use', ['state', 'toolCall', 'isToolExecutionBlocked', 'toolExecution', 'errorList', 'metadata']],
        ['after_tool_use', ['state', 'toolExecution', 'metadata']],
        ['after_step', ['state', 'metadata']],
        ['on_stop', ['state', 'metadata']],
        ['after_execution', ['state', 'metadata']],
        ['on_error', ['state', 'errorList', 'metadata']],
    ]);
});

it('diagnoses disallowed mutations for every trigger without discarding hook output', function () {
    $otherCall = ToolCall::fromArray(['id' => 'call-2', 'name' => 'other', 'arguments' => '{}']);
    $otherRequest = new InferenceRequest(messages: Messages::fromString('other'));

    foreach (hookContractContexts() as $context) {
        $events = new EventDispatcher();
        $violations = [];
        $events->addListener(HookContractViolated::class, function (HookContractViolated $event) use (&$violations): void {
            $violations[] = $event;
        });
        $disallowedField = $context->triggerType() === HookTrigger::BeforeToolUse
            ? 'inferenceRequest'
            : 'toolCall';
        $hook = new CallableHook(
            static fn (HookContext $input): HookContext => match ($input->triggerType()) {
                HookTrigger::BeforeToolUse => $input
                    ->withMetadataEntry('allowed', true)
                    ->withInferenceRequest($otherRequest),
                default => $input
                    ->withMetadataEntry('allowed', true)
                    ->withToolCall($otherCall),
            },
        );
        $stack = (new HookStack(new RegisteredHooks(), $events))
            ->with($hook, HookTriggers::of($context->triggerType()), name: 'contract-test')
            ->withContractDiagnostics();

        $result = $stack->intercept($context);

        expect($result->metadata('allowed'))->toBeTrue()
            ->and($violations)->toHaveCount(1)
            ->and($violations[0]->trigger)->toBe($context->triggerType())
            ->and($violations[0]->hookName)->toBe('contract-test')
            ->and($violations[0]->field)->toBe($disallowedField);
        match ($context->triggerType()) {
            HookTrigger::BeforeToolUse => expect($result->inferenceRequest())->toBe($otherRequest),
            default => expect($result->toolCall())->toBe($otherCall),
        };
    }
});

it('fails deterministically in strict contract mode', function () {
    $context = HookContext::beforeStep(AgentState::empty());
    $call = ToolCall::fromArray(['id' => 'call-3', 'name' => 'other', 'arguments' => '{}']);
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new CallableHook(static fn (HookContext $input): HookContext => $input->withToolCall($call)),
            HookTriggers::beforeStep(),
            name: 'strict-hook',
        )
        ->strict();

    expect(fn () => $stack->intercept($context))
        ->toThrow(
            HookContractViolationException::class,
            "Hook 'strict-hook' cannot mutate 'toolCall' on trigger 'before_step'.",
        );
});

it('enables strict contract mode through AgentBuilder', function () {
    $call = ToolCall::fromArray(['id' => 'call-builder', 'name' => 'other', 'arguments' => '{}']);
    $loop = AgentBuilder::base()
        ->withCapability(new UseHook(
            new CallableHook(static fn (HookContext $input): HookContext => $input->withToolCall($call)),
            HookTriggers::beforeStep(),
            name: 'builder-strict-hook',
        ))
        ->withStrictHookContracts()
        ->build();

    expect(fn () => $loop->interceptor()?->intercept(HookContext::beforeStep(AgentState::empty())))
        ->toThrow(
            HookContractViolationException::class,
            "Hook 'builder-strict-hook' cannot mutate 'toolCall' on trigger 'before_step'.",
        );
});

it('keeps contract diagnostics disabled by default for compatibility', function () {
    $events = new EventDispatcher();
    $violations = [];
    $events->addListener(HookContractViolated::class, function (HookContractViolated $event) use (&$violations): void {
        $violations[] = $event;
    });
    $call = ToolCall::fromArray(['id' => 'call-default', 'name' => 'other', 'arguments' => '{}']);
    $stack = (new HookStack(new RegisteredHooks(), $events))->with(
        new CallableHook(static fn (HookContext $input): HookContext => $input->withToolCall($call)),
        HookTriggers::beforeStep(),
    );

    $result = $stack->intercept(HookContext::beforeStep(AgentState::empty()));

    expect($result->toolCall())->toBe($call)
        ->and($violations)->toBe([]);
});

it('treats the trigger identity as immutable contract data', function () {
    $context = HookContext::beforeStep(AgentState::empty());
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new CallableHook(static fn (HookContext $input): HookContext => new HookContext(
                triggerType: HookTrigger::AfterStep,
                state: $input->state(),
                metadata: $input->metadata(),
                errorList: $input->errorList(),
                createdAt: $input->createdAt(),
            )),
            HookTriggers::beforeStep(),
            name: 'trigger-changing-hook',
        )
        ->strict();

    expect(fn () => $stack->intercept($context))
        ->toThrow(
            HookContractViolationException::class,
            "Hook 'trigger-changing-hook' cannot mutate 'triggerType' on trigger 'before_step'.",
        );
});

it('allows the complete tool-blocking mutation in strict mode and prevents execution', function () {
    $events = new EventDispatcher();
    $violations = [];
    $events->addListener(HookContractViolated::class, function (HookContractViolated $event) use (&$violations): void {
        $violations[] = $event;
    });
    $stack = (new HookStack(new RegisteredHooks(), $events))
        ->with(
            new CallableHook(static fn (HookContext $context): HookContext => $context->blockToolExecution('blocked')),
            HookTriggers::beforeToolUse(),
        )
        ->strict();
    $tools = new Tools(FakeTool::returning('demo', 'Demo', 'executed'));
    $executor = new ToolExecutor($tools, $events, $stack);
    $call = ToolCall::fromArray(['id' => 'call-4', 'name' => 'demo', 'arguments' => '{}']);

    $result = $executor->executeTools(new ToolCalls($call), AgentState::empty())->first();

    expect($violations)->toBe([])
        ->and($result?->hasError())->toBeTrue()
        ->and($result?->value())->toBeNull();
});
