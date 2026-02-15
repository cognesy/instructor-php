<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Tasks\UseTaskPlanning;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('Tasks Capability', function () {
    it('persists tasks deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('todo_write', [
                    'todos' => [
                        [
                            'content' => 'Plan work',
                            'status' => 'in_progress',
                            'activeForm' => 'Planning work',
                        ],
                    ],
                ], executeTools: true),
            ])))
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
