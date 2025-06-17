<?php

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class MixedSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitMixedSchema($this);
    }
}