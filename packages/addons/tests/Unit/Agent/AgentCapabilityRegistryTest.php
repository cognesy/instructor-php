<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Registry\AgentCapabilityRegistry;
use InvalidArgumentException;

final class TestCapability implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        // No-op for tests.
    }
}

describe('AgentCapabilityRegistry', function () {
    it('registers and resolves capability instances', function () {
        $registry = new AgentCapabilityRegistry();
        $capability = new TestCapability();

        $registry->register('tool_discovery', $capability);

        expect($registry->has('tool_discovery'))->toBeTrue();
        expect($registry->resolve('tool_discovery'))->toBe($capability);
    });

    it('registers and resolves capability factories', function () {
        $registry = new AgentCapabilityRegistry();

        $registry->registerFactory('work_context', fn() => new TestCapability());

        $resolved = $registry->resolve('work_context');

        expect($resolved)->toBeInstanceOf(TestCapability::class);
        expect($registry->resolve('work_context'))->toBe($resolved);
    });

    it('rejects factories that return invalid types', function () {
        $registry = new AgentCapabilityRegistry();
        $registry->registerFactory('bad', fn() => new \stdClass());

        $resolve = fn() => $registry->resolve('bad');

        expect($resolve)->toThrow(InvalidArgumentException::class);
    });

    it('rejects missing capabilities', function () {
        $registry = new AgentCapabilityRegistry();

        $resolve = fn() => $registry->resolve('missing');

        expect($resolve)->toThrow(InvalidArgumentException::class);
    });
});
