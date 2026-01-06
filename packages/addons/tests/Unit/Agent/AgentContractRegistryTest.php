<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Contracts\AgentContract;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Definitions\AbstractAgentDefinition;
use Cognesy\Addons\Agent\Exceptions\AgentNotFoundException;
use Cognesy\Addons\Agent\Registry\AgentContractRegistry;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

final class TestAgentDefinition extends AbstractAgentDefinition
{
    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: 'test-agent',
            description: 'Test agent',
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
        return ['ok' => true];
    }

    public static function fromConfig(array $config): Result
    {
        return Result::success(new self());
    }
}

describe('AgentContractRegistry', function () {
    it('rejects non-agent classes', function () {
        $registry = new AgentContractRegistry();

        $register = fn() => $registry->register('bad', \stdClass::class);

        expect($register)->toThrow(InvalidArgumentException::class);
    });

    it('returns failure when agent is missing', function () {
        $registry = new AgentContractRegistry();
        $result = $registry->create('missing', []);

        expect($result->isFailure())->toBeTrue();
        expect($result->exception())->toBeInstanceOf(AgentNotFoundException::class);
    });

    it('creates agent from config', function () {
        $registry = new AgentContractRegistry([
            'test-agent' => TestAgentDefinition::class,
        ]);

        $result = $registry->create('test-agent', ['x' => 1]);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBeInstanceOf(AgentContract::class);
    });
});
