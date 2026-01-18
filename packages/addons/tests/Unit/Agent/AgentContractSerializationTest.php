<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Definitions\AbstractAgentDefinition;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Result\Result;

final class ConfiguredAgentDefinition extends AbstractAgentDefinition
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

    protected function buildAgent(): \Cognesy\Addons\Agent\Agent
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

    public static function fromConfig(array $config): Result
    {
        $workspace = $config['workspace'] ?? '/tmp';
        $maxSteps = $config['max_steps'] ?? 1;

        if (!is_string($workspace) || !is_int($maxSteps)) {
            return Result::failure(new \InvalidArgumentException('Invalid agent config.'));
        }

        return Result::success(new self($workspace, $maxSteps));
    }
}

describe('Agent contract serialization', function () {
    it('round-trips agent config via serializeConfig/fromConfig', function () {
        $config = ['workspace' => '/var/app', 'max_steps' => 3];
        $agent = ConfiguredAgentDefinition::fromConfig($config)->unwrap();

        expect($agent->serializeConfig())->toBe($config);
    });

    it('runs deterministically with config', function () {
        $agent = ConfiguredAgentDefinition::fromConfig([
            'workspace' => '/var/app',
            'max_steps' => 1,
        ])->unwrap();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('ping'));

        $final = $agent->run($state);

        expect($final->stepCount())->toBe(1);
    });
});
