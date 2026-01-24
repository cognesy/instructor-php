<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Agents\Agent\Data\AgentState;
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

        $next = $agent->nextStep(AgentState::empty());
        $tasks = $next->metadata()->get('tasks');

        expect($tasks)->toBeArray();
        expect($tasks[0]['content'] ?? null)->toBe('Plan work');
    });
});
