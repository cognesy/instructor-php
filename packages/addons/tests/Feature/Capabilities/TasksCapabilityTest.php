<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

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
