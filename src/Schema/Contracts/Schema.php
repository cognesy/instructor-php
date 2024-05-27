<?php

namespace Cognesy\Instructor\Schema\Contracts;

use Cognesy\Instructor\Schema\Data\TypeDetails;

interface XSchema extends CanAcceptSchemaVisitor
{
    public function name() : string;
    public function description() : string;
    public function typeDetails() : TypeDetails;

    /** @return array<string, XSchema> */
    public function properties() : array;

    /** @return array<string> */
    public function propertyNames() : array;

    public function property(string $name) : XSchema;
}
