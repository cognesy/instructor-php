<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Definitions\AbstractAgentDefinition;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicDriver;
use Cognesy\Addons\Agent\Events\AgentFinished;
use Cognesy\Addons\Agent\Events\AgentStateUpdated;
use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Utils\Result\Result;
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

final class EventAgentDefinition extends AbstractAgentDefinition
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

    protected function buildAgent(): \Cognesy\Addons\Agent\Agent
    {
        return AgentBuilder::base()
            ->withDriver(new DeterministicDriver())
            ->build();
    }

    public function serializeConfig(): array
    {
        return ['ok' => true];
    }

    public static function fromConfig(array $config): Result
    {
        return Result::success(new self());
    }
}

describe('AbstractAgentDefinition events', function () {
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
        expect($captured)->toContain(AgentFinished::class);
    });

    it('uses provided event handler', function () {
        $definition = new EventAgentDefinition();
        $handler = new TestEventHandler();

        $definition->withEventHandler($handler);
        $definition->wiretap(fn(object $event) => null);

        $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
        $definition->run($state);

        expect($handler->events)->toHaveCount(4);
        expect($handler->events[0])->toBeInstanceOf(AgentStepStarted::class);
    });
});
