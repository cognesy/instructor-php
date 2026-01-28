<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Support\BaseAgent;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Messages\Messages;

final class ConfiguredAgentDefinition extends BaseAgent
{
    public function __construct(
        private readonly string $workspace,
        private readonly int $maxSteps,
    ) {
    }

    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: 'configured-agent',
            description: 'Agent with serialized config',
            tools: new NameList(),
            capabilities: new NameList(),
        );
    }

    protected function buildAgentLoop(): AgentLoop
    {
        return AgentBuilder::base()
            ->withMaxSteps($this->maxSteps)
            ->withDriver(new DeterministicAgentDriver())
            ->build();
    }

    public function serializeConfig(): array
    {
        return [
            'workspace' => $this->workspace,
            'max_steps' => $this->maxSteps,
        ];
    }

    public static function fromConfig(array $config): AgentInterface
    {
        $workspace = $config['workspace'] ?? '/tmp';
        $maxSteps = $config['max_steps'] ?? 1;

        if (!is_string($workspace) || !is_int($maxSteps)) {
            throw new \InvalidArgumentException('Invalid agent config.');
        }

        return new self($workspace, $maxSteps);
    }
}

describe('Agent contract serialization', function () {
    it('round-trips agent config via serializeConfig/fromConfig', function () {
        $config = ['workspace' => '/var/app', 'max_steps' => 3];
        $agent = ConfiguredAgentDefinition::fromConfig($config);

        expect($agent->serializeConfig())->toBe($config);
    });

    it('runs deterministically with config', function () {
        $agent = ConfiguredAgentDefinition::fromConfig([
            'workspace' => '/var/app',
            'max_steps' => 1,
        ]);

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('ping'));

        $final = $agent->run($state);

        expect($final->stepCount())->toBe(1);
    });
});
