<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\AgentExecutionAbandoned;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use RuntimeException;

/**
 * A caller may drop an iterate() generator before it completes. The loop must
 * still run terminal teardown exactly once, so that a supervisor tracking run
 * liveness can observe quiescence.
 */
class LifecycleProbe implements CanInterceptAgentLifecycle
{
    public int $inFlight = 0;
    /** @var list<string> */
    public array $triggers = [];
    /** @var list<AgentExecutionAbandoned> */
    public array $abandoned = [];

    public function intercept(HookContext $context): HookContext
    {
        match ($context->triggerType()) {
            HookTrigger::BeforeExecution => $this->enter(),
            HookTrigger::AfterExecution, HookTrigger::OnAbandoned => $this->leave($context->triggerType()),
            default => null,
        };

        return $context;
    }

    private function enter(): void
    {
        $this->inFlight++;
        $this->triggers[] = HookTrigger::BeforeExecution->value;
    }

    private function leave(HookTrigger $trigger): void
    {
        $this->inFlight--;
        $this->triggers[] = $trigger->value;
    }

    public function countOf(HookTrigger $trigger): int
    {
        return count(array_filter($this->triggers, static fn (string $seen): bool => $seen === $trigger->value));
    }
}

final class ExplodingTeardownProbe extends LifecycleProbe
{
    public function intercept(HookContext $context): HookContext
    {
        if ($context->triggerType() === HookTrigger::OnAbandoned) {
            throw new RuntimeException('teardown hook exploded');
        }

        return parent::intercept($context);
    }
}

function abandonmentLoop(LifecycleProbe $probe): AgentLoop
{
    $tools = (new Tools)->withTool(FakeTool::returning('ping', 'ping', 'pong'));
    $events = new EventDispatcher();
    $events->wiretap(static function (object $event) use ($probe): void {
        if ($event instanceof AgentExecutionAbandoned) {
            $probe->abandoned[] = $event;
        }
    });

    return new AgentLoop(
        tools: $tools,
        toolExecutor: new ToolExecutor($tools, $events, $probe),
        driver: new FakeAgentDriver(
            steps: [
                ScenarioStep::toolCall('ping'),
                ScenarioStep::toolCall('ping'),
                ScenarioStep::toolCall('ping'),
                ScenarioStep::final('done'),
            ],
            tools: $tools,
        ),
        events: $events,
        interceptor: $probe,
    );
}

it('settles through after_execution when the generator is drained', function () {
    $probe = new LifecycleProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    foreach ($states as $_) {
    }

    expect($probe->countOf(HookTrigger::AfterExecution))->toBe(1);
    expect($probe->countOf(HookTrigger::OnAbandoned))->toBe(0);
    expect($probe->inFlight)->toBe(0);
    expect($probe->abandoned)->toBeEmpty();
});

it('settles through on_abandoned when the caller drops the generator mid-stream', function () {
    $probe = new LifecycleProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    $states->current();
    $states->next();

    expect($probe->inFlight)->toBe(1);

    unset($states);

    expect($probe->countOf(HookTrigger::OnAbandoned))->toBe(1);
    expect($probe->countOf(HookTrigger::AfterExecution))->toBe(0);
    expect($probe->inFlight)->toBe(0);
});

it('reports an abandoned execution with the steps it had reached', function () {
    $probe = new LifecycleProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    $states->current();
    $states->next();
    unset($states);

    expect($probe->abandoned)->toHaveCount(1);
    expect($probe->abandoned[0]->totalSteps)->toBe(2);
    expect($probe->abandoned[0]->teardownError)->toBe('');
});

it('does not settle twice when the generator is dropped at the final yield', function () {
    $probe = new LifecycleProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    foreach ($states as $_) {
        if ($probe->countOf(HookTrigger::AfterExecution) === 1) {
            break;
        }
    }

    expect($probe->countOf(HookTrigger::AfterExecution))->toBe(1);

    unset($states);

    expect($probe->countOf(HookTrigger::AfterExecution))->toBe(1);
    expect($probe->countOf(HookTrigger::OnAbandoned))->toBe(0);
    expect($probe->inFlight)->toBe(0);
});

it('settles when the caller throws into the generator', function () {
    $probe = new LifecycleProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    $states->current();

    expect(fn () => $states->throw(new RuntimeException('caller boom')))
        ->toThrow(RuntimeException::class, 'caller boom');

    expect($probe->countOf(HookTrigger::OnAbandoned))->toBe(1);
    expect($probe->inFlight)->toBe(0);
});

it('contains a throwing teardown hook and reports it on the event', function () {
    $probe = new ExplodingTeardownProbe();
    $states = abandonmentLoop($probe)->iterate(AgentState::empty());
    $states->current();
    $states->next();

    unset($states);

    expect($probe->abandoned)->toHaveCount(1);
    expect($probe->abandoned[0]->teardownError)->toBe('teardown hook exploded');
});

it('forbids state mutation from the terminal teardown hook', function () {
    $context = HookContext::onAbandoned(AgentState::empty());

    expect($context->triggerType()->mutableFields())->toBe(['metadata']);
    expect($context->disallowedChangesIn($context->withState(AgentState::empty())))->toContain('state');
});
