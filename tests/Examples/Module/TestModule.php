<?php
namespace Tests\Examples\Module;

use Cognesy\Instructor\Extras\Module\Core\ModuleWithSignature;

class TestModule extends ModuleWithSignature
{
    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
