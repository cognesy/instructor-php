<?php

namespace Tests\Examples\Task;

use Cognesy\Instructor\Extras\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Task\ExecutableTask;

class TestTask extends ExecutableTask
{
    public function __construct() {
        // parent::__construct('numberA:int, numberB:int -> sum:int');
        parent::__construct(SignatureFactory::fromCallable($this->forward(...)));
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
