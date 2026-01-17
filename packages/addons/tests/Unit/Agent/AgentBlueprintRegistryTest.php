<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Contracts\AgentBlueprint;
use Cognesy\Addons\Agent\Definitions\AgentDefinition;
use Cognesy\Addons\Agent\Registry\AgentBlueprintRegistry;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

final class TestBlueprint implements AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): Result
    {
        return Result::success($definition->id);
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

        expect($register)->toThrow(InvalidArgumentException::class);
    });

    it('fails when blueprint alias is missing', function () {
        $registry = new AgentBlueprintRegistry();

        $get = fn() => $registry->get('missing');

        expect($get)->toThrow(InvalidArgumentException::class);
    });
});
