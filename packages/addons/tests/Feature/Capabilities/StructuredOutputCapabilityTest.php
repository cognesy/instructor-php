<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaRegistry;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\StructuredOutputResult;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\UseStructuredOutputs;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

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

        $next = $agent->nextStep(AgentState::empty());
        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];

        expect($executions)->toHaveCount(1);
        $result = $executions[0]->value();
        expect($result)->toBeInstanceOf(StructuredOutputResult::class);
        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Input cannot be empty');
    });
});
