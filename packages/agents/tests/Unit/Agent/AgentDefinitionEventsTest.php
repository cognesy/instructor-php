<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
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

    /** @return iterable<int, callable(object): void> */
    public function getListenersForEvent(object $event): iterable
    {
        $listeners = $this->listeners[$event::class] ?? [];
        $wiretaps = $this->wiretaps;
        return array_merge($listeners, $wiretaps);
    }
}

function makeEventLoop(): AgentLoop
{
    return AgentBuilder::base()
        ->withCapability(new UseDriver(new FakeAgentDriver()))
        ->build();
}

describe('AgentDefinition events', function () {
    it('emits core agent lifecycle events', function () {
        $agent = makeEventLoop();
        $captured = [];
        $completed = 0;

        $agent
            ->wiretap(function (object $event) use (&$captured): void {
                $captured[] = $event::class;
            })
            ->onEvent(AgentStepCompleted::class, function (object $event) use (&$completed): void {
                $completed++;
            });

        $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
        $agent->execute($state);

        expect($completed)->toBe(1);
        expect($captured)->toContain(AgentStepStarted::class);
        expect($captured)->toContain(AgentStepCompleted::class);
        expect($captured)->toContain(ContinuationEvaluated::class);
        expect($captured)->toContain(AgentExecutionStarted::class);
        expect($captured)->toContain(AgentExecutionCompleted::class);
    });

    it('uses provided event handler', function () {
        $handler = new TestEventHandler();
        $agent = makeEventLoop()->withEventHandler($handler);
        $agent->wiretap(fn(object $event) => null);

        $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
        $agent->execute($state);

        $classes = array_map(
            static fn(object $event): string => $event::class,
            $handler->events,
        );

        expect($classes)->toContain(ContinuationEvaluated::class);
        expect($classes)->toContain(AgentStepStarted::class);
    });
});
