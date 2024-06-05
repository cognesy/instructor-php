<?php
namespace Tests\Examples\Module;

use Cognesy\Instructor\Extras\Module\Core\Module;

class TestModule extends Module
{
    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
