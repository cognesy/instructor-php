<?php
namespace Tests\Examples\Module;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

class TestModule extends Module
{
    public function signature(): Signature {
        return SignatureFactory::fromString('numberA:int, numberB:int -> sum:int');
    }

    protected function forward(int $numberA, int $numberB) : int {
        return $numberA + $numberB;
    }
}
