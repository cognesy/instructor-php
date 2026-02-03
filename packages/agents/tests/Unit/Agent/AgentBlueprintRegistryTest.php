<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Support\BaseAgent;
use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentTemplateException;
use Cognesy\Agents\AgentTemplate\Registry\AgentBlueprintRegistry;

final class RegistryAgentDefinition extends BaseAgent
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

    protected function configureLoop(AgentBuilder $builder): AgentBuilder
    {
        return $builder;
    }
}

final class TestBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): \Cognesy\Agents\AgentBuilder\Contracts\AgentInterface
    {
        return new RegistryAgentDefinition($definition->id());
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

        expect($register)->toThrow(AgentTemplateException::class);
    });

    it('fails when blueprint alias is missing', function () {
        $registry = new AgentBlueprintRegistry();

        $get = fn() => $registry->get('missing');

        expect($get)->toThrow(AgentTemplateException::class);
    });
});
