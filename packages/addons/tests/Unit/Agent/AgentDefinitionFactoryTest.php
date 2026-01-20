<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Addons\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionExecution;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionFactory;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionLlm;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionTools;
use Cognesy\Addons\AgentBuilder\Support\AbstractAgent;
use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Addons\AgentTemplate\Exceptions\InvalidAgentBlueprintException;
use Cognesy\Addons\AgentTemplate\Registry\AgentBlueprintRegistry;

final class BlueprintAgentDefinition extends AbstractAgent
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

    protected function buildAgent(): \Cognesy\Addons\Agent\Agent
    {
        return AgentBuilder::base()->build();
    }

    public function serializeConfig(): array
    {
        return ['name' => $this->name];
    }

    public static function fromConfig(array $config): AgentInterface
    {
        $name = $config['name'] ?? 'blueprint-agent';
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Invalid agent config.');
        }

        return new self($name);
    }
}

final class FactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface
    {
        return new BlueprintAgentDefinition('blueprint:' . $definition->id);
    }
}

final class AlternateFactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface
    {
        return new BlueprintAgentDefinition('alt:' . $definition->id);
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

        expect($result)->toBeInstanceOf(AgentInterface::class);
        expect($result->descriptor()->name)->toBe('blueprint:agent-a');
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

        expect($result)->toBeInstanceOf(AgentInterface::class);
        expect($result->descriptor()->name)->toBe('alt:agent-b');
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

        $create = fn() => $factory->create($definition);

        expect($create)->toThrow(AgentBlueprintNotFoundException::class);
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

        $create = fn() => $factory->create($definition);

        expect($create)->toThrow(InvalidAgentBlueprintException::class);
    });
});
