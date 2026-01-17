<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Contracts\AgentBlueprint;
use Cognesy\Addons\Agent\Definitions\AgentDefinition;
use Cognesy\Addons\Agent\Definitions\AgentDefinitionExecution;
use Cognesy\Addons\Agent\Definitions\AgentDefinitionFactory;
use Cognesy\Addons\Agent\Definitions\AgentDefinitionLlm;
use Cognesy\Addons\Agent\Definitions\AgentDefinitionTools;
use Cognesy\Addons\Agent\Registry\AgentBlueprintRegistry;
use Cognesy\Utils\Result\Result;

final class FactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): Result
    {
        return Result::success('blueprint:' . $definition->id);
    }
}

final class AlternateFactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): Result
    {
        return Result::success('alt:' . $definition->id);
    }
}

describe('AgentDefinitionFactory', function () {
    it('resolves blueprint alias via registry', function () {
        $registry = new AgentBlueprintRegistry([
            'basic' => FactoryBlueprint::class,
        ]);

        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            version: 1,
            id: 'agent-a',
            name: 'Agent A',
            description: 'Agent A description',
            systemPrompt: 'Agent A prompt',
            blueprint: 'basic',
            blueprintClass: null,
            llm: new AgentDefinitionLlm('anthropic'),
            execution: new AgentDefinitionExecution(),
            tools: new AgentDefinitionTools(),
        );

        $result = $factory->create($definition);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('blueprint:agent-a');
    });

    it('resolves blueprint_class directly', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            version: 1,
            id: 'agent-b',
            name: 'Agent B',
            description: 'Agent B description',
            systemPrompt: 'Agent B prompt',
            blueprint: null,
            blueprintClass: AlternateFactoryBlueprint::class,
            llm: new AgentDefinitionLlm('anthropic'),
            execution: new AgentDefinitionExecution(),
            tools: new AgentDefinitionTools(),
        );

        $result = $factory->create($definition);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('alt:agent-b');
    });

    it('fails when blueprint alias is missing', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            version: 1,
            id: 'agent-c',
            name: 'Agent C',
            description: 'Agent C description',
            systemPrompt: 'Agent C prompt',
            blueprint: 'missing',
            blueprintClass: null,
            llm: new AgentDefinitionLlm('anthropic'),
            execution: new AgentDefinitionExecution(),
            tools: new AgentDefinitionTools(),
        );

        $result = $factory->create($definition);

        expect($result->isFailure())->toBeTrue();
        expect($result->exception()->getMessage())->toContain('Blueprint');
    });

    it('fails for invalid blueprint_class', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            version: 1,
            id: 'agent-d',
            name: 'Agent D',
            description: 'Agent D description',
            systemPrompt: 'Agent D prompt',
            blueprint: null,
            blueprintClass: \stdClass::class,
            llm: new AgentDefinitionLlm('anthropic'),
            execution: new AgentDefinitionExecution(),
            tools: new AgentDefinitionTools(),
        );

        $result = $factory->create($definition);

        expect($result->isFailure())->toBeTrue();
        expect($result->exception()->getMessage())->toContain('AgentBlueprint');
    });
});
