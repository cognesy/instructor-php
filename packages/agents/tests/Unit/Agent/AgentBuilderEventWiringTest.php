<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Capability\Core\UseReActConfig;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Contracts\CanAcceptEventHandler;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\Data\Usage;
use ReflectionProperty;

final class EventAwareDriver implements CanUseTools, CanAcceptEventHandler
{
    public function __construct(
        private readonly ?CanHandleEvents $events = null,
    ) {}

    #[\Override]
    public function withEventHandler(CanHandleEvents $events): static {
        return new self($events);
    }

    public function eventHandler(): ?CanHandleEvents {
        return $this->events;
    }

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
        return $state->withCurrentStep(new AgentStep(
            inputMessages: Messages::empty(),
            outputMessages: Messages::fromString('ok', 'assistant'),
            inferenceResponse: new InferenceResponse(
                toolCalls: ToolCalls::empty(),
                usage: new Usage(0, 0),
            ),
        ));
    }
}

describe('AgentBuilder event wiring', function () {
    it('does not rebind explicit event-aware drivers to the loop event bus', function () {
        $loop = AgentBuilder::base()
            ->withCapability(new UseDriver(new EventAwareDriver()))
            ->build();

        $driver = $loop->driver();
        expect($driver)->toBeInstanceOf(EventAwareDriver::class);
        expect($driver->eventHandler())->toBeNull();
    });

    it('wires ToolCallingDriver and inference runtime to builder event bus in constructor paths', function () {
        $loop = AgentBuilder::base()
            ->withCapability(new UseLLMConfig())
            ->build();

        $driver = $loop->driver();
        $eventsProperty = new ReflectionProperty($driver, 'events');
        $driverEventsResolver = $eventsProperty->getValue($driver);
        $resolverProperty = new ReflectionProperty($driverEventsResolver, 'eventHandler');
        $driverEvents = $resolverProperty->getValue($driverEventsResolver);

        $inferenceProperty = new ReflectionProperty($driver, 'inference');
        $inferenceRuntime = $inferenceProperty->getValue($driver);
        $inferenceEventsProperty = new ReflectionProperty($inferenceRuntime, 'events');
        $inferenceEventsResolver = $inferenceEventsProperty->getValue($inferenceRuntime);
        $inferenceResolverProperty = new ReflectionProperty($inferenceEventsResolver, 'eventHandler');
        $inferenceEvents = $inferenceResolverProperty->getValue($inferenceEventsResolver);

        expect($driverEvents)->toBe($loop->eventHandler());
        expect($inferenceEvents)->toBe($loop->eventHandler());
    });

    it('passes constructor-provided ReAct runtimes into driver wiring unchanged', function () {
        $runtimeEvents = EventBusResolver::using(null);
        $inferenceRuntime = InferenceRuntime::fromProvider(
            provider: LLMProvider::new(),
            events: $runtimeEvents,
        );
        $structuredRuntime = new StructuredOutputRuntime(
            inference: $inferenceRuntime,
            events: $runtimeEvents,
            config: (new StructuredOutputConfigBuilder())->create(),
        );

        $loop = AgentBuilder::base()
            ->withCapability(new UseReActConfig(
                inference: $inferenceRuntime,
                structuredOutput: $structuredRuntime,
            ))
            ->build();

        $driver = $loop->driver();
        expect($driver)->toBeInstanceOf(ReActDriver::class);

        $inferenceProperty = new ReflectionProperty($driver, 'inference');
        $driverInferenceRuntime = $inferenceProperty->getValue($driver);
        expect($driverInferenceRuntime)->toBe($inferenceRuntime);

        $structuredProperty = new ReflectionProperty($driver, 'structuredOutput');
        $driverStructuredRuntime = $structuredProperty->getValue($driver);
        expect($driverStructuredRuntime)->toBe($structuredRuntime);
    });
});
