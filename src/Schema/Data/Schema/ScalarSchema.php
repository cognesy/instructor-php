<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;

class ScalarSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitScalarSchema($this);
    }
}
