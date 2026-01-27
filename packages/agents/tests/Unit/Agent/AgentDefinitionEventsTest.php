<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Events\AgentExecutionCompleted;
use Cognesy\Agents\Core\Events\AgentExecutionStarted;
use Cognesy\Agents\Core\Events\AgentStateUpdated;
use Cognesy\Agents\Core\Events\AgentStepCompleted;
use Cognesy\Agents\Core\Events\AgentStepStarted;
use Cognesy\Agents\Core\Events\ContinuationEvaluated;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Support\AbstractAgent;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Messages\Messages;

final class TestEventHandler implements CanHandleEvents
{
    /** @var array<int, callable(object): void> */
    private array $wiretaps = [];
    /** @var array<string, array<int, callable(object): void>> */
    private array $listeners = [];
    /** @var array<int, object> */
    public array $events = [];

    public function addListener(string $name, callable $listener, int $priority = 0): void
    {
        $this->listeners[$name][] = $listener;
    }

    public function wiretap(callable $listener): void
    {
        $this->wiretaps[] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->events[] = $event;
        return $event;
    }

    public function getListenersForEvent(object $event): iterable
    {
        $listeners = $this->listeners[$event::class] ?? [];
        $wiretaps = $this->wiretaps;
        return array_merge($listeners, $wiretaps);
    }
}

final class EventAgentDefinition extends AbstractAgent
{
    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: 'event-agent',
            description: 'Event agent',
            tools: new NameList(),
            capabilities: new NameList(),
        );
    }

    protected function buildAgent(): \Cognesy\Agents\Agent\Agent
    {
        return AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver())
            ->build();
    }

    public function serializeConfig(): array
    {
        return ['ok' => true];
    }

    public static function fromConfig(array $config): AgentInterface
    {
        return new self();
    }
}

describe('AbstractAgent events', function () {
    it('emits core agent lifecycle events', function () {
        $definition = new EventAgentDefinition();
        $captured = [];
        $completed = 0;

        $definition
            ->wiretap(function (object $event) use (&$captured): void {
                $captured[] = $event::class;
            })
            ->onEvent(AgentStepCompleted::class, function (object $event) use (&$completed): void {
                $completed++;
            });

        $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
        $definition->run($state);

        expect($completed)->toBe(1);
        expect($captured)->toContain(AgentStepStarted::class);
        expect($captured)->toContain(AgentStateUpdated::class);
        expect($captured)->toContain(AgentStepCompleted::class);
        expect($captured)->toContain(ContinuationEvaluated::class);
        expect($captured)->toContain(AgentExecutionStarted::class);
        expect($captured)->toContain(AgentExecutionCompleted::class);
    });

    it('uses provided event handler', function () {
        $definition = new EventAgentDefinition();
        $handler = new TestEventHandler();

        $definition->withEventHandler($handler);
        $definition->wiretap(fn(object $event) => null);

        $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
        $definition->run($state);

        $classes = array_map(
            static fn(object $event): string => $event::class,
            $handler->events,
        );

        expect($classes)->toContain(ContinuationEvaluated::class);
        expect($classes)->toContain(AgentStepStarted::class);
    });
});
