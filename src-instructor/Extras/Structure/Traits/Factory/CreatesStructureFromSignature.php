<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait CreatesStructureFromSignature
{
    static public function fromSignature(string $name, Signature $signature) : Structure {
        return StructureFactory::fromSchema($name, $signature->toOutputSchema(), $signature->getDescription());
    }
}