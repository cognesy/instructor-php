<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;

class ObjectRefSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitObjectRefSchema($this);
    }
}