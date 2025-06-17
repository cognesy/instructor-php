<?php

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class ScalarSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitScalarSchema($this);
    }
}
