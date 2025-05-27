<?php

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class EnumSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitEnumSchema($this);
    }
}
