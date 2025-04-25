<?php

namespace Cognesy\Experimental\Tests\Examples\Module;

class TestModule extends ModuleWithSignature
{
    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
