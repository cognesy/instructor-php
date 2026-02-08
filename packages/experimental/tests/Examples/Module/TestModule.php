<?php

namespace Cognesy\Experimental\Tests\Examples\Module;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Core\ModuleCall;

class TestModule extends Module
{
    public function withArgs(int $numberA, int $numberB): ModuleCall {
        return $this(numberA: $numberA, numberB: $numberB);
    }

    protected function forward(mixed ...$callArgs) : array {
        $args = is_array($callArgs[0] ?? null) ? $callArgs[0] : $callArgs;
        $numberA = (int) ($args['numberA'] ?? 0);
        $numberB = (int) ($args['numberB'] ?? 0);
        return ['result' => $numberA + $numberB];
    }
}
