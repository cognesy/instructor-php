<?php

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class ObjectRefSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitObjectRefSchema($this);
    }
}