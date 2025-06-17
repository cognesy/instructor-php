<?php

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class OptionSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitOptionSchema($this);
    }
}