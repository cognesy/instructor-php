<?php

namespace Tests\Examples\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\SignatureFactory;

class TestTask extends \Cognesy\Instructor\Extras\Tasks\Task\ExecutableTask
{
    public function __construct() {
        // parent::__construct('numberA:int, numberB:int -> sum:int');
        parent::__construct(SignatureFactory::fromCallable($this->forward(...)));
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
