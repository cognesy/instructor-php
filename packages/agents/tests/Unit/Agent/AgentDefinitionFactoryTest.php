<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Support\BaseAgent;
use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionFactory;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentTemplateException;
use Cognesy\Agents\AgentTemplate\Registry\AgentBlueprintRegistry;

final class BlueprintAgentDefinition extends BaseAgent
{
    public function __construct(private readonly string $name)
    {
    }

    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: $this->name,
            description: 'Blueprint test agent',
            tools: new NameList(),
            capabilities: new NameList(),
        );
    }

    protected function configureLoop(AgentBuilder $builder): AgentBuilder
    {
        return $builder;
    }
}

final class FactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface
    {
        return new BlueprintAgentDefinition('blueprint:' . $definition->id());
    }
}

final class AlternateFactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface
    {
        return new BlueprintAgentDefinition('alt:' . $definition->id());
    }
}

describe('AgentDefinitionFactory', function () {
    it('resolves blueprint alias via registry', function () {
        $registry = new AgentBlueprintRegistry([
            'basic' => FactoryBlueprint::class,
        ]);

        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            name: 'Agent A',
            description: 'Agent A description',
            systemPrompt: 'Agent A prompt',
            blueprint: 'basic',
            id: 'agent-a',
        );

        $result = $factory->create($definition);

        expect($result)->toBeInstanceOf(AgentInterface::class);
        expect($result->descriptor()->name)->toBe('blueprint:agent-a');
    });

    it('resolves blueprint_class directly', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            name: 'Agent B',
            description: 'Agent B description',
            systemPrompt: 'Agent B prompt',
            blueprintClass: AlternateFactoryBlueprint::class,
            id: 'agent-b',
        );

        $result = $factory->create($definition);

        expect($result)->toBeInstanceOf(AgentInterface::class);
        expect($result->descriptor()->name)->toBe('alt:agent-b');
    });

    it('fails when blueprint alias is missing', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            name: 'Agent C',
            description: 'Agent C description',
            systemPrompt: 'Agent C prompt',
            blueprint: 'missing',
            id: 'agent-c',
        );

        $create = fn() => $factory->create($definition);

        expect($create)->toThrow(AgentTemplateException::class);
    });

    it('fails for invalid blueprint_class', function () {
        $registry = new AgentBlueprintRegistry();
        $factory = new AgentDefinitionFactory($registry);
        $definition = new AgentDefinition(
            name: 'Agent D',
            description: 'Agent D description',
            systemPrompt: 'Agent D prompt',
            blueprintClass: \stdClass::class,
            id: 'agent-d',
        );

        $create = fn() => $factory->create($definition);

        expect($create)->toThrow(AgentTemplateException::class);
    });
});
