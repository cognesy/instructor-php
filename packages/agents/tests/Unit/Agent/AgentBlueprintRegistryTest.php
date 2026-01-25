<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Support\AbstractAgent;
use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Agents\AgentTemplate\Exceptions\InvalidAgentBlueprintException;
use Cognesy\Agents\AgentTemplate\Registry\AgentBlueprintRegistry;

final class RegistryAgentDefinition extends AbstractAgent
{
    public function __construct(private readonly string $name)
    {
    }

    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: $this->name,
            description: 'Registry test agent',
            tools: new NameList(),
            capabilities: new NameList(),
        );
    }

    protected function buildAgent(): \Cognesy\Agents\Agent\Agent
    {
        return AgentBuilder::base()->build();
    }

    public function serializeConfig(): array
    {
        return ['name' => $this->name];
    }

    public static function fromConfig(array $config): AgentInterface
    {
        $name = $config['name'] ?? 'registry-agent';
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Invalid agent config.');
        }

        return new self($name);
    }
}

final class TestBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): AgentInterface
    {
        return new RegistryAgentDefinition($definition->id);
    }
}

describe('AgentBlueprintRegistry', function () {
    it('registers and resolves blueprints', function () {
        $registry = new AgentBlueprintRegistry();
        $registry->register('basic', TestBlueprint::class);

        expect($registry->has('basic'))->toBeTrue();
        expect($registry->get('basic'))->toBe(TestBlueprint::class);
        expect($registry->names())->toBe(['basic']);
    });

    it('rejects non-blueprint classes', function () {
        $registry = new AgentBlueprintRegistry();

        $register = fn() => $registry->register('bad', \stdClass::class);

        expect($register)->toThrow(InvalidAgentBlueprintException::class);
    });

    it('fails when blueprint alias is missing', function () {
        $registry = new AgentBlueprintRegistry();

        $get = fn() => $registry->get('missing');

        expect($get)->toThrow(AgentBlueprintNotFoundException::class);
    });
});
