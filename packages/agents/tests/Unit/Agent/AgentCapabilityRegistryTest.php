<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use InvalidArgumentException;

final class TestCapabilityCanProvide implements CanProvideAgentCapability
{
    public static function capabilityName(): string {
        return 'test_capability';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent;
    }
}

describe('AgentCapabilityRegistry', function () {
    it('registers and gets capability instances', function () {
        $registry = new AgentCapabilityRegistry();
        $capability = new TestCapabilityCanProvide();

        $registry->register('tool_discovery', $capability);

        expect($registry->has('tool_discovery'))->toBeTrue();
        expect($registry->get('tool_discovery'))->toBe($capability);
    });

    it('registers and gets capability factories', function () {
        $registry = new AgentCapabilityRegistry();

        $registry->registerFactory('work_context', fn() => new TestCapabilityCanProvide());

        $resolved = $registry->get('work_context');

        expect($resolved)->toBeInstanceOf(TestCapabilityCanProvide::class);
        expect($registry->get('work_context'))->toBe($resolved);
    });

    it('rejects factories that return invalid types', function () {
        $registry = new AgentCapabilityRegistry();
        $registry->registerFactory('bad', fn() => new \stdClass());

        $resolve = fn() => $registry->get('bad');

        expect($resolve)->toThrow(InvalidArgumentException::class);
    });

    it('rejects missing capabilities', function () {
        $registry = new AgentCapabilityRegistry();

        $resolve = fn() => $registry->get('missing');

        expect($resolve)->toThrow(InvalidArgumentException::class);
    });
});
