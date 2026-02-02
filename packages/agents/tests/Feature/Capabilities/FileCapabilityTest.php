<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;

describe('File Capability', function () {
    it('executes file tools deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('edit_file', [
                    'path' => 'file.txt',
                    'old_string' => '',
                    'new_string' => 'x',
                ], executeTools: true),
            ]))
            ->withCapability(new UseFileTools())
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1);
        expect($executions[0]->name())->toBe('edit_file');
        expect($executions[0]->value())->toBe('Error: old_string cannot be empty');
    });
})->skip('hooks not integrated yet');
