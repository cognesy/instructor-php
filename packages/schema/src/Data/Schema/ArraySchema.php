<?php
namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class ArraySchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitArraySchema($this);
    }
}
