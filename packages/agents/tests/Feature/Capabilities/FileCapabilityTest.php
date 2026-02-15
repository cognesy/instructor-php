<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\File\UseFileTools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('File Capability', function () {
    it('executes file tools deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('edit_file', [
                    'path' => 'file.txt',
                    'old_string' => '',
                    'new_string' => 'x',
                ], executeTools: true),
            ])))
            ->withCapability(new UseFileTools(sys_get_temp_dir()))
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $executions = $next->lastStepToolExecutions()->all();
        expect($executions)->toHaveCount(1);
        expect($executions[0]->name())->toBe('edit_file');
        expect($executions[0]->value())->toBe('Error: old_string cannot be empty');
    });
});
