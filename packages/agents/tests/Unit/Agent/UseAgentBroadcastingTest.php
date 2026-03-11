<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Capability\Broadcasting\UseAgentBroadcasting;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;

it('registers broadcast observers through the builder capability', function () {
    $transport = new class implements CanBroadcastAgentEvents {
        /** @var array<int, array{channel: string, envelope: array}> */
        public array $calls = [];

        public function broadcast(string $channel, array $envelope): void
        {
            $this->calls[] = ['channel' => $channel, 'envelope' => $envelope];
        }
    };

    $agent = AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('done')))
        ->withCapability(new UseAgentBroadcasting(
            broadcaster: $transport,
            sessionId: 'session-1',
        ))
        ->build();

    $agent->execute(AgentState::empty()->withUserMessage('hello'));

    $types = array_map(
        static fn(array $call): string => $call['envelope']['type'],
        $transport->calls,
    );

    expect($types)->toContain('agent.status');
    expect($types)->toContain('agent.step.started');
    expect($types)->toContain('agent.step.completed');

    $lastStatus = array_values(array_filter(
        $transport->calls,
        static fn(array $call): bool => $call['envelope']['type'] === 'agent.status',
    ));

    expect($lastStatus[array_key_last($lastStatus)]['envelope']['payload']['status'])->toBe('completed');
});
