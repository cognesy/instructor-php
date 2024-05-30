<?php
namespace Tests\Examples\Module;

use Cognesy\Instructor\Extras\Module\Task\ExecutableTask;

class TestTask extends ExecutableTask
{
    public function signature(): string {
        return 'numberA:int, numberB:int -> sum:int';
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
