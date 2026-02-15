<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Template;

use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Utils\Metadata;

describe('DefinitionStateFactory', function () {
    it('instantiates state from definition and seed state', function () {
        $definition = new AgentDefinition(
            name: 'researcher',
            description: 'Research agent',
            systemPrompt: 'You are a research assistant.',
            budget: new AgentBudget(maxSteps: 5),
            metadata: Metadata::fromArray(['tier' => 'gold']),
        );

        $seed = AgentState::empty()
            ->withUserMessage('hello')
            ->withMetadata('session', 'abc')
            ->withBudget(new AgentBudget(maxSteps: 10));

        $state = (new DefinitionStateFactory())->instantiateAgentState($definition, $seed);

        expect($state->context()->systemPrompt())->toBe('You are a research assistant.')
            ->and($state->messages()->count())->toBe(1)
            ->and($state->metadata()->get('tier'))->toBe('gold')
            ->and($state->metadata()->get('session'))->toBe('abc')
            ->and($state->budget()->maxSteps)->toBe(5);
    });
});
