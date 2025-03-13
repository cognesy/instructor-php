<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait ConvertsToStructure
{
    static public function toStructure(string $name, Signature $signature) : Structure {
        return StructureFactory::fromSchema($name, $signature->toOutputSchema(), $signature->getDescription());
    }
}