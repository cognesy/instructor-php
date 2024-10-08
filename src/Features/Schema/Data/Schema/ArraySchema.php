<?php
namespace Cognesy\Instructor\Features\Schema\Data\Schema;

use Cognesy\Instructor\Features\Schema\Contracts\CanVisitSchema;

class ArraySchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitArraySchema($this);
    }
}
