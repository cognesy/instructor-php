<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseLlmConfig;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Contracts\CanAcceptEventHandler;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
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
    it('rebinds explicit event-aware drivers to the final loop event bus', function () {
        $loop = AgentBuilder::base()
            ->withCapability(new UseDriver(new EventAwareDriver()))
            ->build();

        $driver = $loop->driver();

        expect($driver)->toBeInstanceOf(EventAwareDriver::class)
            ->and($driver->eventHandler())->toBe($loop->eventHandler());
    });

    it('rebinds ToolCallingDriver events provided by UseLlmConfig during build', function () {
        $loop = AgentBuilder::base()
            ->withCapability(new UseLlmConfig())
            ->build();

        $driver = $loop->driver();
        $eventsProperty = new ReflectionProperty($driver, 'events');
        $driverEventsResolver = $eventsProperty->getValue($driver);
        $resolverProperty = new ReflectionProperty($driverEventsResolver, 'eventHandler');
        $driverEvents = $resolverProperty->getValue($driverEventsResolver);

        expect($driverEvents)->toBe($loop->eventHandler());
    });
});
