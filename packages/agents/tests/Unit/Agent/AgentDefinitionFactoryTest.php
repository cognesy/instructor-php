<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Support\BaseAgent;
use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition as TemplateAgentDefinition;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionExecution;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionFactory;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionLlm;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionTools;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Agents\AgentTemplate\Exceptions\InvalidAgentBlueprintException;
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

    protected function buildAgentLoop(): AgentLoop
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
    public static function fromDefinition(TemplateAgentDefinition $definition): AgentInterface
    {
        return new BlueprintAgentDefinition('blueprint:' . $definition->id);
    }
}

final class AlternateFactoryBlueprint implements AgentBlueprint
{
    public static function fromDefinition(TemplateAgentDefinition $definition): AgentInterface
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
        $definition = new TemplateAgentDefinition(
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
        $definition = new TemplateAgentDefinition(
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
        $definition = new TemplateAgentDefinition(
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
        $definition = new TemplateAgentDefinition(
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
