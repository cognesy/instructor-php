<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

describe('File Capability', function () {
    it('executes file tools deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('edit_file', [
                    'path' => 'file.txt',
                    'old_string' => '',
                    'new_string' => 'x',
                ], executeTools: true),
            ]))
            ->withCapability(new UseFileTools())
            ->build();

        $next = $agent->nextStep(AgentState::empty());

        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1);
        expect($executions[0]->name())->toBe('edit_file');
        expect($executions[0]->value())->toBe('Error: old_string cannot be empty');
    });
});
