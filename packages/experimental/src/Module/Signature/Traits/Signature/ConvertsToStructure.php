<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Experimental\Module\Signature\Signature;

trait ConvertsToStructure
{
    static public function toStructure(string $name, Signature $signature) : Structure {
        return StructureFactory::fromSchema($name, $signature->toOutputSchema(), $signature->getDescription());
    }
}