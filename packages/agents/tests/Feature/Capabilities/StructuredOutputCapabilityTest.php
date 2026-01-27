<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput\StructuredOutputResult;
use Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput\UseStructuredOutputs;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

describe('StructuredOutput Capability', function () {
    it('executes structured output tool deterministically with validation failure', function () {
        $schemas = new SchemaRegistry([
            'demo' => \stdClass::class,
        ]);

        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('structured_output', [
                    'input' => '',
                    'schema' => 'demo',
                ], executeTools: true),
            ]))
            ->withCapability(new UseStructuredOutputs($schemas))
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];

        expect($executions)->toHaveCount(1);
        $result = $executions[0]->value();
        expect($result)->toBeInstanceOf(StructuredOutputResult::class);
        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Input cannot be empty');
    });
});
