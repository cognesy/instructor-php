<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\StructuredOutput\StructuredOutputResult;
use Cognesy\Agents\Capability\StructuredOutput\UseStructuredOutputs;
use Cognesy\Agents\Capability\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('StructuredOutput Capability', function () {
    it('executes structured output tool deterministically with validation failure', function () {
        $schemas = new SchemaRegistry([
            'demo' => \stdClass::class,
        ]);

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('structured_output', [
                    'input' => '',
                    'schema' => 'demo',
                ], executeTools: true),
            ])))
            ->withCapability(new UseStructuredOutputs(
                schemas: $schemas,
                structuredOutput: StructuredOutputRuntime::fromProvider(LLMProvider::new()),
            ))
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $executions = $next->lastStepToolExecutions()->all();

        expect($executions)->toHaveCount(1);
        $result = $executions[0]->value();
        expect($result)->toBeInstanceOf(StructuredOutputResult::class);
        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Input cannot be empty');
    });
});
