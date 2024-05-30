<?php
namespace Tests\Examples\Module;

use Cognesy\Instructor\Extras\Module\Core\Module;

class TestTask extends Module
{
    public function signature(): string {
        return 'numberA:int, numberB:int -> sum:int';
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
