<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

describe('Tasks Capability', function () {
    it('persists tasks deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('todo_write', [
                    'todos' => [
                        [
                            'content' => 'Plan work',
                            'status' => 'in_progress',
                            'activeForm' => 'Planning work',
                        ],
                    ],
                ], executeTools: true),
            ]))
            ->withCapability(new UseTaskPlanning())
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $tasks = $next->metadata()->get('tasks');

        expect($tasks)->toBeArray();
        expect($tasks[0]['content'] ?? null)->toBe('Plan work');
    });
});
