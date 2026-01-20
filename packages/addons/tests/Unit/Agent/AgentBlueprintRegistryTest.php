<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Addons\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Addons\AgentBuilder\Support\AbstractAgent;
use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Addons\AgentTemplate\Exceptions\InvalidAgentBlueprintException;
use Cognesy\Addons\AgentTemplate\Registry\AgentBlueprintRegistry;

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
