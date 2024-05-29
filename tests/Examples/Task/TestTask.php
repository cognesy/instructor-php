<?php
namespace Tests\Examples\Task;

use Cognesy\Instructor\Extras\Tasks\Task\ExecutableTask;

class TestTask extends ExecutableTask
{
    public function signature(): string {
        return 'numberA:int, numberB:int -> sum:int';
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
