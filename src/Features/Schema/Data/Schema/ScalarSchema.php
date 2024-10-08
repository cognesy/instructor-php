<?php

namespace Cognesy\Instructor\Features\Schema\Data\Schema;

use Cognesy\Instructor\Features\Schema\Contracts\CanVisitSchema;

class ScalarSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitScalarSchema($this);
    }
}
